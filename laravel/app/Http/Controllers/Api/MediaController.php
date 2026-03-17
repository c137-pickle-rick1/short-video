<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\ShortVideo\Services\MediaProxyService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class MediaController extends Controller
{
    public function show(string $tweetId, Request $request, MediaProxyService $mediaProxyService): Response|JsonResponse|StreamedResponse
    {
        $result = $mediaProxyService->getMediaStream($tweetId, $request->header('Range', ''));

        if (($result['kind'] ?? 'json') === 'json') {
            return response()->json($result['body'] ?? ['error' => 'Unexpected error'], (int) ($result['status'] ?? 500));
        }

        /** @var \Psr\Http\Message\StreamInterface $stream */
        $stream = $result['body'];

        return response()->stream(
            function () use ($mediaProxyService, $stream): void {
                foreach ($mediaProxyService->stream($stream) as $chunk) {
                    echo $chunk;
                    flush();
                }
            },
            (int) $result['status'],
            $result['headers'] ?? []
        );
    }
}
