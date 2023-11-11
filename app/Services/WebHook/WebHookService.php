<?php

namespace App\Services\WebHook;

;
use App\Models\Webhook;
use Illuminate\Support\Facades\Log;
use App\Services\Stock\StockService;
class WebHookService
{
    protected $stockService;

    public function __construct(StockService $stockService)
    {
        $this->stockService = $stockService;
    }
    public function index($request)
    {
        $payload = $request->getContent();
        $payload_array = json_decode($payload, true);

        $advertiserId = $payload_array['data']['advertiser']['advertiserId'] ?? 0;
        $client_advertiserid = config('services.autotrader.advertiserid');
        if($advertiserId == $client_advertiserid)
        {
            $this->store($payload_array);
            return $this->stockService->update($payload_array);
        }
        else
        {
            Log::info('Advertiserid not match ', ['AdvId' => $advertiserId, 'clientid' => $client_advertiserid]);
        }
    }

    public function store($payload)
    {
        Webhook::create([
            'webhook_id' => $payload['id'],
            'type' => $payload['type'],
            'body' => $payload,
        ]);
        Log::info('Webhook event saved successfully', ['webhook_id' => $payload['id']]);
    }
}
