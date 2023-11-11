<?php

namespace App\Services\Stock;

use App\Repositories\Stock\StockRepository;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class StockService
{
    protected $stockRepository;

    public function __construct(StockRepository $stockRepository)
    {
        $this->stockRepository = $stockRepository;
    }
    public function index($request)
    {
        if (Cache::has('autotrader_authenticated'))
        {
            $cachedItem = Cache::get('autotrader_authenticated');
            if ($cachedItem)
            {
                $expirationTime = Cache::get('autotrader_authenticated' . ':expires_at');
                $expirationDate = Carbon::parse($expirationTime);
                Log::info('Cache expiration date: ' . $expirationDate);
                $auth_response = ['message' => 'already authenticated', 'token_expiration' => $expirationDate];
            }
            else
            {
                Log::info('Cache does not exist.');
            }
        }
        else
        {
          $auth_response = self::AutotraderAuthentication($request);
        }

        $fetch = $this->stockRepository->findAll($request);
        return array('auth' => $auth_response, 'stock' => $fetch);
    }

    public function show()
    {
        return $this->stockRepository->findAllLocal();
    }

    public function findBy($searchId)
    {
        return $this->stockRepository->findById($searchId);
    }

    public function update($payload)
    {
        if ($payload['type'] === 'STOCK_UPDATE')
        {
            $webhook_id = $payload['id'];
            return $this->stockRepository->update($webhook_id, $payload);
        }
    }

    public function AutotraderAuthentication($request)
    {
        $data = [
            'key' => config('services.autotrader.api_key'),
            'secret' => config('services.autotrader.api_secret')
        ];

//        $response = Http::post('https://api-sandbox.autotrader.co.uk/authenticate', $data);
        $base_url = config('services.autotrader.base_url');
        $end_point = '/authenticate';
        $url = $base_url.$end_point;

        $headers = [
            'Content-Type' => 'application/x-www-form-urlencoded',
        ];

        $response = Http::withHeaders($headers)->post($url, $data);

        if ($response->failed())
        {
            Log::info('API Authentication failed', ['response' =>  $response->json()]);
            return ['message' => 'API Authentication failed', 'response' =>  $response->json()];
        }
        else
        {
            $response_data = $response->json();
            if (isset($response_data['access_token']))
            {
                $request->headers->set('AutotradersToken', 'Bearer ' . $response_data['access_token']);
                $expires = Carbon::parse($response_data['expires'])->utc();
                Cache::put('autotrader_authenticated', 'Bearer ' . $response_data['access_token'], $expires);
                Log::info('api expire time: ' . $response_data['expires']. ' -- '.'expires carbon timestamp: ' . $expires);
            }
            return $response;
        }
    }

}
