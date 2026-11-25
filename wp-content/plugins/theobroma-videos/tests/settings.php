<?php
function sanitize_text_field($v) { return trim(strip_tags((string) $v)); }
function sanitize_textarea_field($v) { return trim(strip_tags((string) $v)); }
function absint($v) { return abs((int) $v); }
function esc_url_raw($v, $protocols = null) { return preg_match('~^(https?://|/)~', $v) ? $v : ''; }
require dirname(__DIR__) . '/settings.php';
function check($value, $message) { if (!$value) { throw new RuntimeException($message); } }
$s = theobroma_videos_sanitize(['stories' => ['enabled' => '1', 'title' => '<b>Истории</b>', 'items' => [
 ['title' => '<i>Первый</i>', 'enabled' => '1', 'video' => 'https://example.com/video.mp4', 'poster_id' => '-5', 'button_enabled' => '1', 'button_text' => 'Купить', 'button_url' => 'javascript:alert(1)'],
 ['title' => 'Второй', 'video' => 'https://example.com/2.mp4', 'button_text' => 'Сохранённый текст', 'button_url' => '/catalog/'],
]]]);
check($s['stories']['title'] === 'Истории', 'Clean heading');
check($s['stories']['items'][0]['button_url'] === '', 'Reject script URL');
check($s['stories']['items'][0]['poster_id'] === 5, 'Normalize attachment');
check(!$s['stories']['items'][1]['enabled'], 'Unchecked visibility');
check(!$s['stories']['items'][1]['button_enabled'] && $s['stories']['items'][1]['button_text'] === 'Сохранённый текст', 'Disabled button retains settings');
check($s['reels']['items'] === [] && !$s['reels']['enabled'], 'Missing section safely disabled');
check(theobroma_videos_sanitize('invalid')['stories']['items'] === [], 'Malformed input');
check(count(theobroma_videos_sanitize(['reels'=>['items'=>array_fill(0, 40, ['title'=>'Test'])]])['reels']['items']) === 30, 'Bound number of records');
echo "Video settings tests passed\n";
