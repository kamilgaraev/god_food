#!/usr/bin/env python3
"""Theobroma host-side health and error alerts. Uses only Python stdlib."""

import argparse
import json
import os
import re
import smtplib
import ssl
import subprocess
import sys
from datetime import datetime, timezone
from email.message import EmailMessage
from pathlib import Path
from urllib.request import Request, urlopen


NGINX_STATUS = re.compile(r'"(?:GET|POST|HEAD|PUT|PATCH|DELETE) [^\"]+ HTTP/[\d.]+" (\d{3}) ')
PHP_FATAL = re.compile(r'PHP (?:Fatal|Parse) error|Uncaught (?:Error|Exception)', re.I)
JS_ERROR = 'THEOBROMA_JS_ERROR'


def read_new_access_log(path, state):
    stat = path.stat()
    old = state.get('nginx', {})
    if not old:
        offset = stat.st_size
    elif old.get('inode') == stat.st_ino:
        offset = old.get('offset', stat.st_size)
    else:
        offset = 0
    with path.open('rb') as stream:
        stream.seek(min(offset, stat.st_size))
        lines = stream.readlines()
        end = stream.tell()
    state['nginx'] = {'inode': stat.st_ino, 'offset': end}
    return lines


def count_5xx(lines):
    return sum(
        1 for line in lines
        if (match := NGINX_STATUS.search(line.decode('utf-8', 'replace')))
        and int(match.group(1)) >= 500
    )


def count_runtime_errors(logs):
    return (sum(bool(PHP_FATAL.search(line)) for line in logs.splitlines()),
            sum(JS_ERROR in line for line in logs.splitlines()))


def check_homepage(url):
    request = Request(url, headers={'User-Agent': 'Theobroma-Monitor/1.0'})
    with urlopen(request, timeout=12) as response:
        if response.status != 200 or 'text/html' not in response.headers.get('Content-Type', ''):
            raise RuntimeError(f'HTTP {response.status} или неожиданный Content-Type')
        if not response.read(128 * 1024):
            raise RuntimeError('Пустой ответ сайта')


def send_mail(config, subject, body):
    message = EmailMessage()
    message['From'] = config['from']
    message['To'] = config['to']
    message['Subject'] = subject
    message.set_content(body)
    with smtplib.SMTP(config['host'], int(config['port']), timeout=20) as smtp:
        smtp.starttls(context=ssl.create_default_context())
        smtp.login(config['username'], config['password'])
        smtp.send_message(message)


def record_event(state, level, title, detail):
    events = state.setdefault('events', [])
    events.append({
        'at': datetime.now(timezone.utc).isoformat(),
        'level': level,
        'title': title,
        'detail': detail[:200],
    })
    state['events'] = events[-40:]


def update_alert(state, key, problem, threshold, detail, config):
    failures = state.setdefault('failures', {})
    active = state.setdefault('active', {})
    failures[key] = failures.get(key, 0) + 1 if problem else 0
    if problem and failures[key] >= threshold and not active.get(key):
        send_mail(config, f'[Theobroma] Проблема: {key}', detail)
        active[key] = True
        record_event(state, 'error', key, detail)
        print(f'ALERT {key}: {detail}')
    elif not problem and active.get(key):
        send_mail(config, f'[Theobroma] Восстановлено: {key}', f'Проверка «{key}» снова в норме.\n{detail}')
        active[key] = False
        record_event(state, 'success', key, 'Показатель вернулся в норму.')
        print(f'RECOVERED {key}')


def configured_recipient(config, state):
    code = 'require "wp-load.php"; echo (string) get_option("theobroma_monitor_alert_email", "");'
    try:
        result = subprocess.run(
            ['docker', 'exec', '-w', '/var/www/html', config['container'], 'php', '-r', code],
            capture_output=True, text=True, timeout=15, check=True,
        )
        email = result.stdout.strip()
        if email and re.fullmatch(r'[^\s@]+@[^\s@]+\.[^\s@]+', email):
            state['recipient'] = email
    except Exception as exc:
        print(f'Cannot refresh alert recipient: {exc}', file=sys.stderr)
    return state.get('recipient') or config['to']


def publish_dashboard(config, state):
    payload = {
        'last_check': state['last_check'],
        'checks': state.get('checks', [])[-24:],
        'events': state.get('events', [])[-30:],
        'active': [key for key, value in state.get('active', {}).items() if value],
    }
    code = ('require "wp-load.php"; '
            '$data = json_decode(stream_get_contents(STDIN), true); '
            'if (is_array($data)) update_option("theobroma_monitor_snapshot", $data, false);')
    subprocess.run(
        ['docker', 'exec', '-i', '-w', '/var/www/html', config['container'], 'php', '-r', code],
        input=json.dumps(payload, ensure_ascii=False), capture_output=True,
        text=True, timeout=20, check=True,
    )


def run(config, state_path):
    now = datetime.now(timezone.utc).isoformat()
    state = json.loads(state_path.read_text('utf-8')) if state_path.exists() else {}
    try:
        check_homepage(config['url'])
        health_error = ''
    except Exception as exc:
        health_error = str(exc)[:200]

    log_error = ''
    try:
        nginx_lines = read_new_access_log(Path(config['nginx_access_log']), state)
    except Exception as exc:
        nginx_lines = []
        log_error = f'nginx: {exc}'
    previous = state.get('last_docker_time', now)
    try:
        result = subprocess.run(
            ['docker', 'logs', '--since', previous, '--timestamps', '--tail', '2000', config['container']],
            capture_output=True, text=True, timeout=25, check=True,
        )
        state['last_docker_time'] = now
        php_count, js_count = count_runtime_errors(result.stdout + result.stderr)
    except Exception as exc:
        php_count = js_count = 0
        log_error = f'{log_error}; docker: {exc}' if log_error else f'docker: {exc}'
    http_count = count_5xx(nginx_lines)
    config = {**config, 'to': configured_recipient(config, state)}

    if http_count:
        record_event(state, 'warning', 'Ответы HTTP 5xx', f'Новых ответов: {http_count}.')
    if php_count:
        record_event(state, 'error', 'Ошибки PHP', f'Новых критических ошибок: {php_count}.')
    if js_count:
        record_event(state, 'warning', 'Ошибки JavaScript', f'Новых ошибок: {js_count}.')

    update_alert(state, 'Доступность сайта', bool(health_error), 2,
                 f'{config["url"]}: {health_error or "HTTP 200"}', config)
    update_alert(state, 'Сбор логов', bool(log_error), 1,
                 (log_error or 'nginx и Docker доступны')[:200], config)
    update_alert(state, 'Ошибки HTTP 5xx', http_count >= 3, 1,
                 f'За последние 2 минуты: {http_count} новых ответов 5xx.', config)
    update_alert(state, 'Критические ошибки PHP', php_count >= 1, 1,
                 f'За последние 2 минуты: {php_count} новых критических ошибок PHP.', config)
    update_alert(state, 'Ошибки JavaScript', js_count >= 3, 1,
                 f'За последние 2 минуты: {js_count} новых ошибок JavaScript.', config)

    check = {
        'at': now,
        'site': 'down' if health_error else 'up',
        'http_5xx': http_count,
        'php': php_count,
        'js': js_count,
        'logs': 'error' if log_error else 'ok',
    }
    state['last_check'] = check
    state['checks'] = (state.get('checks', []) + [check])[-24:]
    state_path.parent.mkdir(parents=True, exist_ok=True)
    temp = state_path.with_suffix('.tmp')
    fd = os.open(temp, os.O_WRONLY | os.O_CREAT | os.O_TRUNC, 0o600)
    with os.fdopen(fd, 'w', encoding='utf-8') as stream:
        json.dump(state, stream, ensure_ascii=False)
    os.replace(temp, state_path)
    try:
        publish_dashboard(config, state)
    except Exception as exc:
        print(f'Cannot publish admin dashboard: {exc}', file=sys.stderr)
    print(f'OK: homepage={"error" if health_error else "200"}, 5xx={http_count}, php={php_count}, js={js_count}')


def main():
    parser = argparse.ArgumentParser()
    parser.add_argument('--config', type=Path, required=True)
    parser.add_argument('--state', type=Path, required=True)
    parser.add_argument('--self-test', action='store_true')
    args = parser.parse_args()
    config = json.loads(args.config.read_text('utf-8'))
    if args.self_test:
        saved_state = json.loads(args.state.read_text('utf-8')) if args.state.exists() else {}
        config = {**config, 'to': configured_recipient(config, saved_state)}
        send_mail(config, '[Theobroma] Проверка оповещений',
                  'Автоматический мониторинг сайта установлен. Это тестовое письмо.')
        print('Test alert sent')
        return
    run(config, args.state)


if __name__ == '__main__':
    try:
        main()
    except Exception as exc:
        print(f'Monitor failed: {exc}', file=sys.stderr)
        sys.exit(1)
