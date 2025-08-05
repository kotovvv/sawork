<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Dotenv\Dotenv;

class ApiConfigServiceProvider extends ServiceProvider
{
  /**
   * Register services.
   */
  public function register(): void
  {
    //
  }

  /**
   * Bootstrap services.
   */
  public function boot(): void
  {
    // Load additional .env.api file if it exists
    $apiEnvPath = base_path('.env.api');

    if (file_exists($apiEnvPath)) {
      $dotenv = Dotenv::createImmutable(base_path(), '.env.api');
      $dotenv->safeLoad();
    }
  }
}
