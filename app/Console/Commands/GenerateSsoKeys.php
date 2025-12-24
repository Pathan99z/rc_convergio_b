<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class GenerateSsoKeys extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sso:generate-keys';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate RSA key pair for SSO (private and public keys)';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $keysPath = storage_path('app/keys');
        
        // Create keys directory if it doesn't exist
        if (!File::exists($keysPath)) {
            File::makeDirectory($keysPath, 0755, true);
            $this->info('Created keys directory: ' . $keysPath);
        }

        // Check if keys already exist
        $privateKeyPath = $keysPath . '/sso-private.pem';
        $publicKeyPath = $keysPath . '/sso-public.pem';

        if (File::exists($privateKeyPath) || File::exists($publicKeyPath)) {
            if (!$this->confirm('Keys already exist. Do you want to overwrite them?', false)) {
                $this->info('Operation cancelled.');
                return Command::SUCCESS;
            }
        }

        // Generate private key with Windows/XAMPP OpenSSL fix
        $config = [
            'digest_alg' => 'sha256',
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ];

        // Fix for Windows/XAMPP: Set OpenSSL config if not set
        if (PHP_OS_FAMILY === 'Windows' && !getenv('OPENSSL_CONF')) {
            // Try to find OpenSSL config in common XAMPP locations
            $possibleConfigs = [
                'C:/xampp/apache/bin/openssl.cnf',
                'C:/xampp/php/extras/openssl/openssl.cnf',
                'C:/Program Files/OpenSSL-Win64/bin/openssl.cfg',
                'C:/OpenSSL-Win64/bin/openssl.cfg',
            ];
            
            foreach ($possibleConfigs as $configPath) {
                if (file_exists($configPath)) {
                    $config['config'] = $configPath;
                    break;
                }
            }
            
            // If no config found, create a minimal config
            if (!isset($config['config'])) {
                $config['config'] = $this->createMinimalOpenSSLConfig();
            }
        }

        $resource = openssl_pkey_new($config);
        
        if (!$resource) {
            $error = openssl_error_string();
            $this->error('Failed to generate private key. Error: ' . $error);
            $this->warn('');
            $this->warn('Troubleshooting:');
            $this->warn('1. Make sure OpenSSL extension is enabled in php.ini');
            $this->warn('2. Check if openssl.cnf exists in XAMPP directory');
            $this->warn('3. Try setting OPENSSL_CONF environment variable');
            return Command::FAILURE;
        }

        // Export private key
        $exportSuccess = openssl_pkey_export($resource, $privateKey, null, $config);
        
        if (!$exportSuccess) {
            $this->error('Failed to export private key. Error: ' . openssl_error_string());
            return Command::FAILURE;
        }
        
        // Get public key
        $publicKeyDetails = openssl_pkey_get_details($resource);
        if (!$publicKeyDetails) {
            $this->error('Failed to get public key details. Error: ' . openssl_error_string());
            return Command::FAILURE;
        }
        $publicKey = $publicKeyDetails['key'];

        // Save private key
        File::put($privateKeyPath, $privateKey);
        if (PHP_OS_FAMILY !== 'Windows') {
            chmod($privateKeyPath, 0600); // Read/write for owner only
        }
        
        // Save public key
        File::put($publicKeyPath, $publicKey);
        if (PHP_OS_FAMILY !== 'Windows') {
            chmod($publicKeyPath, 0644); // Readable by all, writable by owner
        }

        $this->info('✅ RSA keys generated successfully!');
        $this->line('');
        $this->line('Private key: ' . $privateKeyPath);
        $this->line('Public key: ' . $publicKeyPath);
        $this->line('');
        $this->warn('⚠️  IMPORTANT: Share the PUBLIC key with Console team.');
        $this->warn('⚠️  Keep the PRIVATE key secure and never commit it to git!');
        $this->line('');
        $this->info('Next steps:');
        $this->line('1. Copy public key to Console server: /keys/public.pem');
        $this->line('2. Add Console DB credentials to .env file');
        $this->line('3. Run migrations: php artisan migrate');

        return Command::SUCCESS;
    }

    /**
     * Create minimal OpenSSL config for Windows
     */
    private function createMinimalOpenSSLConfig(): string
    {
        $configPath = storage_path('app/keys/openssl.cnf');
        
        $configContent = <<<'CONFIG'
[req]
distinguished_name = req_distinguished_name
[req_distinguished_name]
[v3_ca]
[v3_req]
CONFIG;

        File::put($configPath, $configContent);
        
        return $configPath;
    }
}

