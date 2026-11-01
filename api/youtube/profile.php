<?php
declare(strict_types=1);
require dirname(__DIR__).'/bootstrap.php';
hf_require_method('GET'); hf_session();
hf_api_run(function(): void {
    $response=hf_youtube('channels',['part'=>'snippet','mine'=>'true','maxResults'=>1]);
    $channel=$response['items'][0] ?? null;
    if (!$channel) hf_json(['channel'=>null]);
    $sn=$channel['snippet'] ?? [];
    hf_json(['channel'=>['id'=>$channel['id'],'title'=>$sn['title'] ?? 'YouTube user',
        'thumbnail'=>$sn['thumbnails']['default']['url'] ?? null]]);
});
