<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Mailjet\Client;
use Mailjet\Resources;

class EmailService
{
    public function sendEmail($to, $subject, $templateId, $variables = [])
    {
        $mj = new Client(env('MAILJET_APIKEY'), env('MAILJET_APISECRET'), true, ['version' => 'v3.1']);

        $body = [
            'Messages' => [
                [
                    'From' => [
                        'Email' => 'admin@amanueld.info',
                        'Name' => env('APP_NAME') ?? 'AmanD',
                    ],
                    'To' => [
                        [
                            'Email' => $to,
                            'Name' => $variables['name'] ?? 'User',
                        ],
                    ],
                    'TemplateID' => $templateId,
                    'TemplateLanguage' => true,
                    'Subject' => $subject,
                    'Variables' => $variables,
                ],
            ],
        ];

        $response = $mj->post(Resources::$Email, ['body' => $body]);

        // dd($response);

        if (! $response->success()) {
            Log::error('Failed to send email', ['response' => $response->getData()]);

            return false;
        }

        return true;
    }
}
