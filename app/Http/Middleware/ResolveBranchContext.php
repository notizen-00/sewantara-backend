<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ResolveBranchContext
{
    public const HEADER = 'X-Branch-Id';

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $requestedBranchId = $request->header(self::HEADER);

        if ($requestedBranchId !== null
            && (! ctype_digit((string) $requestedBranchId)
                || (int) $requestedBranchId < 1)) {
            return $this->error(
                'BRANCH_HEADER_INVALID',
                'Header X-Branch-Id harus berisi ID cabang yang valid.',
                422,
            );
        }

        $branch = $user->branches()
            ->where('is_active', true)
            ->when(
                $requestedBranchId !== null,
                fn ($query) => $query->where('branches.id', (int) $requestedBranchId),
            )
            ->when(
                $requestedBranchId === null,
                fn ($query) => $query
                    ->orderByDesc('branch_users.is_primary')
                    ->orderBy('branches.id'),
            )
            ->first();

        if (! $branch) {
            return $this->error(
                'BRANCH_ACCESS_DENIED',
                $requestedBranchId === null
                    ? 'Anda belum memiliki akses ke cabang aktif.'
                    : 'Anda tidak memiliki akses ke cabang yang dipilih.',
                403,
            );
        }

        app()->instance('currentBranch', $branch);
        $request->attributes->set('branch', $branch);

        try {
            $response = $next($request);
            $response->headers->set(self::HEADER, (string) $branch->getKey());

            return $response;
        } finally {
            app()->forgetInstance('currentBranch');
        }
    }

    private function error(string $code, string $message, int $status): JsonResponse
    {
        return response()->json([
            'success' => false,
            'error' => [
                'code' => $code,
                'message' => $message,
                'details' => null,
            ],
        ], $status);
    }
}
