<?php

namespace App\Repositories\Stock;

use App\Repositories\Interface\StockInterface;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use App\Models\Stock;
use Illuminate\Support\Facades\Log;

class StockRepository implements StockInterface
{
    /**
     * @param $params
     * @return mixed
     */
    public function findAll($request)
    {
        $token = Cache::get('autotrader_authenticated');
        $page = Cache::get('last_processed_page', 1);
        $pageSize = 20;

        $advertiserId = config('services.autotrader.advertiserid');
        $base_url = config('services.autotrader.base_url');
//        $baseApiUrl = 'https://api-sandbox.autotrader.co.uk';
        $baseApiUrl =  $base_url;

        do {
            $queryParams = [
                'advertiserId' => $advertiserId,
                'page' => $page,
                'pageSize' => $pageSize,
            ];

            $queryString = http_build_query($queryParams);
            $stockEndpoint = "{$baseApiUrl}/stock?{$queryString}";

            $response = Http::withHeaders([
                'Authorization' => $token,
            ])->get($stockEndpoint);

            if ($response->failed()) {
                Log::info('Stock fetch API request failed');
                return  ['message' => 'Stock fetch API request failed', 'response' => $response];
            }

            $batch = $response->json()['results'];
            $totalResults = $response->json()['totalResults'];
            $totalPages = ceil($totalResults / $pageSize);

            if (!empty($batch)) {
                $this->create($batch);
            }

            $page++;
            Cache::put('last_processed_page', $page, now()->addMinutes(15));

        } while ($page <= $totalPages);

        return ['message' => 'Stock data fetched successfully', "totalpage" => $totalPages, 'currentpage' =>  $page];
    }

    /**
     * @param array $attributes
     * @return mixed
     */
    public function create(array $attributes)
    {
        foreach ($attributes as $record) {
            Stock::firstOrCreate(
                [
                    'stockId' => $record['metadata']['stockId'],
                ],
                [
                    'vehicle' => $record['vehicle'],
                    'advertiser' => $record['advertiser'],
                    'adverts' => $record['adverts'],
                    'metadata' => $record['metadata'],
                    'searchId' => $record['metadata']['searchId'],
                    'features' => $record['features'],
                    'media' => $record['media'],
                    'history' => $record['history'],
                    'check' => $record['check'],
                ]
            );
        }

    }

    /**
     * @param $id
     * @param array $attributes
     * @return mixed
     */
    public function update($id, array $attributes)
    {
        $stock = Stock::where('stockId', $id)->first();
        if ($stock)
        {
            $stock->update(
                [
                'vehicle' => $attributes['data']['vehicle'],
                'advertiser' => $attributes['data']['advertiser'],
                'adverts' => $attributes['data']['adverts'],
                'metadata' => $attributes['data']['metadata'],
                'features' => $attributes['data']['features'],
                'media' => $attributes['data']['media'],
                'check' => $attributes['data']['check'],
                ]
            );

            Log::info('Stock record updated successfully', ['stockId' => $id]);
            $response = [
                'message' => 'Stock record updated successfully',
                'id' => $id
            ];
        }
        else
        {
            Stock::create([
                'stockId' => $id,
                'vehicle' => $attributes['data']['vehicle'],
                'advertiser' => $attributes['data']['advertiser'],
                'adverts' => $attributes['data']['adverts'],
                'metadata' => $attributes['data']['metadata'],
                'searchId' => $attributes['data']['metadata']['searchId'],
                'features' => $attributes['data']['features'],
                'media' => $attributes['data']['media'],
                'check' => $attributes['data']['check'],
            ]);
            Log::info('New Stock added in webhook successfully', ['stockId' => $id]);
            $response = [
                'message' => 'New stock added in webhook successfully',
                'id' => $id
            ];
        }
        return $response;
    }

    /**
     * @param $id
     * @return mixed
     */
    public function findById($id)
    {
        // Find the stock record based on the given stockId
        $stock = Stock::where('searchId', $id)->first();

        if (!$stock) {
            return response()->json(['message' => 'Stock not found'], 404);
        }
        return response()->json($stock);
    }

    /**
     * @return mixed
     */
    public function findAllLocal()
    {
        return Stock::whereJsonContains('adverts->retailAdverts->advertiserAdvert->status', 'PUBLISHED')
            ->paginate(20);
    }

    /**
     * @param $id
     * @return mixed
     */
    public function destroy($id)
    {
        // TODO: Implement destroy() method.
    }
}
