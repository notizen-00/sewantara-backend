<?php

namespace App\Http\Controllers\Api\Public;

use App\Http\Requests\Api\Public\StorePublicQuoteRequest;
use App\Modules\PublicApi\Application\CreatePublicQuote;
use App\Modules\PublicApi\Exceptions\PublicApiException;
use Illuminate\Http\JsonResponse;

class QuoteStoreController extends PublicTransactionalController
{
    public function __invoke(
        StorePublicQuoteRequest $request,
        CreatePublicQuote $quotes,
    ): JsonResponse {
        try {
            return $this->success(
                $request,
                $quotes->execute($this->tenantId($request), $request->validated()),
                201,
            );
        } catch (PublicApiException $exception) {
            return $this->error($request, $exception);
        }
    }
}
