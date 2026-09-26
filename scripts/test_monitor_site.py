import tempfile
import unittest
from pathlib import Path
from unittest.mock import Mock

from monitor_site import (configured_recipient, count_5xx, count_runtime_errors,
                          publish_dashboard, read_new_access_log, record_event, update_alert)


class MonitorTests(unittest.TestCase):
    def test_access_log_reads_only_new_lines_and_rotated_file(self):
        with tempfile.TemporaryDirectory() as directory:
            path = Path(directory) / 'access.log'
            path.write_bytes(b'old line\n')
            state = {}
            self.assertEqual(read_new_access_log(path, state), [])
            with path.open('ab') as stream:
                stream.write(b'new line\n')
            self.assertEqual(read_new_access_log(path, state), [b'new line\n'])
            path.rename(Path(directory) / 'access.log.1')
            path.write_bytes(b'rotated line\n')
            self.assertEqual(read_new_access_log(path, state), [b'rotated line\n'])

    def test_http_and_runtime_error_counts(self):
        lines = [
            b'127.0.0.1 - - [date] "GET / HTTP/2.0" 200 123\n',
            b'127.0.0.1 - - [date] "POST /checkout HTTP/1.1" 502 1\n',
            b'127.0.0.1 - - [date] "GET /oops HTTP/2.0" 500 1\n',
        ]
        self.assertEqual(count_5xx(lines), 2)
        self.assertEqual(count_runtime_errors('PHP Fatal error: broken\nTHEOBROMA_JS_ERROR {}\n'), (1, 1))

    def test_alert_threshold_deduplication_and_recovery(self):
        state = {}
        sender = Mock()
        import monitor_site
        original = monitor_site.send_mail
        monitor_site.send_mail = sender
        try:
            update_alert(state, 'site', True, 2, 'down', {})
            self.assertEqual(sender.call_count, 0)
            update_alert(state, 'site', True, 2, 'down', {})
            update_alert(state, 'site', True, 2, 'down', {})
            self.assertEqual(sender.call_count, 1)
            update_alert(state, 'site', False, 2, 'up', {})
            self.assertEqual(sender.call_count, 2)
        finally:
            monitor_site.send_mail = original

    def test_recipient_is_refreshed_and_cached(self):
        import monitor_site
        original = monitor_site.subprocess.run
        monitor_site.subprocess.run = Mock(return_value=Mock(stdout='alerts@example.org\n'))
        try:
            state = {}
            self.assertEqual(configured_recipient({'container': 'wordpress', 'to': 'admin@example.org'}, state),
                             'alerts@example.org')
            self.assertEqual(state['recipient'], 'alerts@example.org')
        finally:
            monitor_site.subprocess.run = original

    def test_dashboard_contains_safe_summaries_not_recipient(self):
        import json
        import monitor_site
        original = monitor_site.subprocess.run
        runner = Mock()
        monitor_site.subprocess.run = runner
        try:
            state = {'recipient': 'private@example.org', 'last_check': {'site': 'up'},
                     'checks': [{'site': 'up'}], 'events': []}
            publish_dashboard({'container': 'wordpress'}, state)
            payload = json.loads(runner.call_args.kwargs['input'])
            self.assertEqual(payload['last_check']['site'], 'up')
            self.assertNotIn('recipient', payload)
            record_event(state, 'warning', 'PHP', 'one error')
            self.assertEqual(state['events'][-1]['title'], 'PHP')
        finally:
            monitor_site.subprocess.run = original


if __name__ == '__main__':
    unittest.main()
