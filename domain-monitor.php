<?php
require 'vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;
use Monolog\Handler\RotatingFileHandler;

class DomainMonitor {
    private $domain;
    private $checkInterval;
    private $notificationEmail;
    private $lastCheck;
    private $logger;
    private $mailer;
    private $config;
    
    public function __construct(array $config) {
        $this->config = $config;
        $this->validateConfig();
        
        $this->domain = $this->config['domain']['name'];
        $this->checkInterval = $this->config['domain']['check_interval'];
        $this->notificationEmail = $this->config['email']['recipient'];
        $this->lastCheck = 0;
        
        $this->initializeLogger();
        $this->setupMailer();
    }
    
    private function validateConfig() {
        $requiredKeys = [
            'domain' => ['name', 'check_interval', 'whois_server', 'whois_port', 'whois_timeout'],
            'email' => ['recipient', 'smtp'],
            'email.smtp' => ['host', 'port', 'username', 'password', 'encryption', 'from_email', 'from_name'],
            'logging' => ['directory', 'filename', 'max_files', 'level']
        ];
        
        foreach ($requiredKeys as $section => $keys) {
            $config = $this->config;
            if (str_contains($section, '.')) {
                $parts = explode('.', $section);
                foreach ($parts as $part) {
                    $config = $config[$part] ?? [];
                }
            } else {
                $config = $config[$section] ?? [];
            }
            
            foreach ($keys as $key) {
                if (!isset($config[$key])) {
                    throw new Exception("Missing required configuration: $section.$key");
                }
            }
        }
    }
    
    private function initializeLogger() {
        $this->logger = new Logger('DomainMonitor');
        
        // Ensure log directory exists
        if (!file_exists($this->config['logging']['directory'])) {
            mkdir($this->config['logging']['directory'], 0777, true);
        }
        
        // Add rotating file handler
        $this->logger->pushHandler(new RotatingFileHandler(
            $this->config['logging']['directory'] . '/' . $this->config['logging']['filename'],
            $this->config['logging']['max_files'],
            $this->getLogLevel($this->config['logging']['level'])
        ));
        
        // Add console handler if enabled
        if ($this->config['logging']['console_output'] ?? false) {
            $this->logger->pushHandler(new StreamHandler('php://stdout', 
                $this->getLogLevel($this->config['logging']['level'])
            ));
        }
    }
    
    private function getLogLevel($level) {
        $levels = [
            'debug' => Logger::DEBUG,
            'info' => Logger::INFO,
            'warning' => Logger::WARNING,
            'error' => Logger::ERROR
        ];
        return $levels[strtolower($level)] ?? Logger::INFO;
    }
    
    private function setupMailer() {
        $this->mailer = new PHPMailer(true);
        
        try {
            $smtp = $this->config['email']['smtp'];
            
            $this->mailer->isSMTP();
            $this->mailer->Host = $smtp['host'];
            $this->mailer->SMTPAuth = true;
            $this->mailer->Username = $smtp['username'];
            $this->mailer->Password = $smtp['password'];
            $this->mailer->SMTPSecure = $smtp['encryption'];
            $this->mailer->Port = $smtp['port'];
            
            $this->mailer->setFrom($smtp['from_email'], $smtp['from_name']);
            $this->logger->info('PHPMailer configured successfully');
        } catch (Exception $e) {
            $this->logger->error('Mailer configuration failed: ' . $e->getMessage());
            throw new Exception('Failed to configure email system: ' . $e->getMessage());
        }
    }
    
    public function checkDomain() {
        $this->logger->info("Checking availability for domain: {$this->domain}");
        
        try {
            $connection = @fsockopen(
                $this->config['domain']['whois_server'],
                $this->config['domain']['whois_port'],
                $errno,
                $errstr,
                $this->config['domain']['whois_timeout']
            );
            
            if (!$connection) {
                $this->logger->error("WHOIS connection failed: $errstr");
                throw new Exception("Could not connect to WHOIS server: $errstr");
            }
            
            fwrite($connection, $this->domain . "\r\n");
            $response = "";
            while (!feof($connection)) {
                $response .= fgets($connection, 128);
            }
            fclose($connection);
            
            $isAvailable = !preg_match("/Domain Name: {$this->domain}/i", $response);
            $this->logger->info("Domain {$this->domain} availability check completed. Available: " . 
                ($isAvailable ? 'Yes' : 'No'));
            
            return $isAvailable;
        } catch (Exception $e) {
            $this->logger->error("Error checking domain: " . $e->getMessage());
            throw $e;
        }
    }
    
    public function notify() {
        try {
            $this->logger->info("Preparing to send notification email for domain: {$this->domain}");
            
            $this->mailer->addAddress($this->notificationEmail);
            $this->mailer->Subject = "Domain {$this->domain} is now available!";
            
            $message = "The domain {$this->domain} appears to be available for registration.\n";
            $message .= "Please check and register it quickly if you're interested.\n";
            $message .= "Timestamp: " . date('Y-m-d H:i:s');
            
            $this->mailer->Body = $message;
            $this->mailer->send();
            
            $this->logger->info("Notification email sent successfully to {$this->notificationEmail}");
            
            // Clear all addresses for next use
            $this->mailer->clearAddresses();
        } catch (Exception $e) {
            $this->logger->error("Failed to send notification email: " . $e->getMessage());
            throw new Exception("Failed to send notification: " . $e->getMessage());
        }
    }
    
    public function monitor() {
        $this->logger->info("Starting domain monitoring for {$this->domain}");
        
        while (true) {
            if (time() - $this->lastCheck >= $this->checkInterval) {
                try {
                    if ($this->checkDomain()) {
                        $this->notify();
                        $this->logger->info("Domain {$this->domain} is available! Notification sent.");
                        break;
                    } else {
                        $this->logger->info("Domain {$this->domain} is still not available. " . 
                            "Next check in " . ($this->checkInterval / 60) . " minutes.");
                    }
                    $this->lastCheck = time();
                } catch (Exception $e) {
                    $this->logger->error("Error in monitoring loop: " . $e->getMessage());
                }
            }
            sleep(60);
        }
    }
}

// Usage example
try {
    $config = require 'config.php';
    $monitor = new DomainMonitor($config);
    $monitor->monitor();
} catch (Exception $e) {
    error_log("Fatal error in domain monitor: " . $e->getMessage());
    die("Fatal error: " . $e->getMessage() . "\n");
}
?>
