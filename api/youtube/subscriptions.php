<?php
declare(strict_types=1);
require dirname(__DIR__).'/bootstrap.php';
hf_require_method('GET');
hf_session();
hf_api_run(function():void {
    $params=['part'=>'snippet','mine'=>'true','maxResults'=>50];
    $token=hf_page_token();
    if ($token!=='') $params['pageToken']=$token;
    $response=hf_youtube('subscriptions',$params);
    $items=[];
    foreach ($response['items'] ?? [] as $item) {
        $sn=$item['snippet'] ?? [];
        $id=$sn['resourceId']['channelId'] ?? null;
        if (!$id) continue;
        $items[]=['id'=>$id,'title'=>$sn['title'] ?? 'Channel',
            'thumbnail'=>$sn['thumbnails']['default']['url'] ?? null];
    }
    hf_json(['items'=>$items,'nextPageToken'=>$response['nextPageToken'] ?? null]);
});
