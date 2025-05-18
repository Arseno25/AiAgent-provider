<?php

namespace AiAgent\Console\Commands;

use AiAgent\Facades\AiAgent;
use Illuminate\Console\Command;

class AiProvidersCommand extends Command
{
  /**
   * The name and signature of the console command.
   *
   * @var string
   */
  protected $signature = 'ai:providers';

  /**
   * The console command description.
   *
   * @var string
   */
  protected $description = 'List all available AI providers';

  /**
   * Execute the console command.
   */
  public function handle()
  {
    $providers = AiAgent::getProviderNames();
    $defaultProvider = config('ai-agent.default_provider');

    $this->info('Available AI Providers:');
    $this->newLine();

    $tableData = [];
    foreach ($providers as $provider) {
      $tableData[] = [
        'provider' => $provider,
        'default' => $provider === $defaultProvider ? 'Yes' : 'No',
        'enabled' => config("ai-agent.providers.{$provider}.enabled", true) ? 'Yes' : 'No',
      ];
    }

    $this->table(['Provider', 'Default', 'Enabled'], $tableData);

    $this->newLine();
    $this->info('Default provider: ' . $defaultProvider);
    $this->info('Total providers: ' . count($providers));
  }
}
