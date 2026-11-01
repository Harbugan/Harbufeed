<?php
declare(strict_types=1);
require dirname(__DIR__).'/bootstrap.php';
hf_require_method('GET'); hf_session();
hf_api_run(function(): void {
    $channelId=(string)($_GET['channelId'] ?? '');
    if (!preg_match('/^UC[a-zA-Z0-9_-]{22}$/',$channelId)) hf_error(400,'Invalid channel');
    $token=hf_page_token();
    $channel=hf_youtube('channels',['part'=>'contentDetails','id'=>$channelId,'maxResults'=>1]);
    $playlist=$channel['items'][0]['contentDetails']['relatedPlaylists']['uploads'] ?? null;
    if (!$playlist) hf_json(['items'=>[],'nextPageToken'=>null]);
    $params=['part'=>'snippet,contentDetails','playlistId'=>$playlist,'maxResults'=>50];
    if ($token!=='') $params['pageToken']=$token;
    $page=hf_youtube('playlistItems',$params);
    $ids=[];
    foreach ($page['items'] ?? [] as $entry) {
        $id=$entry['contentDetails']['videoId'] ?? null;
        if (is_string($id) && preg_match('/^[a-zA-Z0-9_-]{11}$/',$id)) $ids[]=$id;
    }
    if (!$ids) hf_json(['items'=>[],'nextPageToken'=>$page['nextPageToken'] ?? null]);
    $details=hf_youtube('videos',['part'=>'snippet,contentDetails,status','id'=>implode(',',$ids),'maxResults'=>50]);
    $items=[];
    foreach ($details['items'] ?? [] as $v) {
        if (($v['status']['embeddable'] ?? true) === false) continue;
        $s=$v['snippet'] ?? [];
        $duration=new DateInterval($v['contentDetails']['duration'] ?? 'PT0S');
        $start=new DateTimeImmutable('@0');
        $seconds=$start->add($duration)->getTimestamp();
        $items[]=[
            'id'=>'youtube:'.$v['id'],'videoId'=>$v['id'],'source'=>'youtube',
            'title'=>$s['title'] ?? 'Video','channel'=>$s['channelTitle'] ?? '',
            'channelId'=>$s['channelId'] ?? $channelId,'topic'=>'YouTube',
            'seconds'=>$seconds,'minutes'=>(int)ceil($seconds/60),
            'published'=>substr((string)($s['publishedAt'] ?? ''),0,10),
            'thumbnail'=>$s['thumbnails']['medium']['url'] ?? null,
            'shortCandidate'=>$seconds<=180
        ];
    }
    hf_json(['items'=>$items,'nextPageToken'=>$page['nextPageToken'] ?? null]);
});
