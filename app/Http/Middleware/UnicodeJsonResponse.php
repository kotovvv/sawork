<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Http\JsonResponse;

class UnicodeJsonResponse
{
  /**
   * Handle an incoming request.
   *
   * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
   */
  public function handle(Request $request, Closure $next): Response
  {
    $response = $next($request);

    // If the response is JSON, re-encode it with proper Unicode support
    if ($response instanceof JsonResponse) {
      $data = $response->getData();
      $response->setData($data);

      // Force re-encoding with Unicode flags
      $encodingOptions = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES;
      $response->setEncodingOptions($encodingOptions);
    }

    return $response;
  }
}
