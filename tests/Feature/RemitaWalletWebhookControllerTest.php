<?php

namespace Tests\Feature\Payment\Providers\RemitaWallet;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Symfony\Component\HttpFoundation\Response;
use \Autepos\AiPayment\Providers\RemitaWallet\Tests\TestCase;
use Autepos\AiPayment\Providers\RemitaWallet\Events\RemitaWalletWebhookHandled;
use Autepos\AiPayment\Providers\RemitaWallet\Events\RemitaWalletWebhookReceived;
use Autepos\AiPayment\Providers\RemitaWallet\Http\Controllers\RemitaWalletWebhookController;


class RemitaWalletWebhookControllerTest extends TestCase
{
    public function test_proper_methods_are_called_based_on_remita_event()
    {
        $request = $this->request();

        Event::fake([
            RemitaWalletWebhookHandled::class,
            RemitaWalletWebhookReceived::class,
        ]);

        $response = (new WebhookControllerTestStub)->handleWebhook($request,'test.succeeded');

        Event::assertDispatched(RemitaWalletWebhookReceived::class, function (RemitaWalletWebhookReceived $event) use ($request) {
            return $request->getContent() == json_encode($event->payload);
        });

        Event::assertDispatched(RemitaWalletWebhookHandled::class, function (RemitaWalletWebhookHandled $event) use ($request) {
            return $request->getContent() == json_encode($event->payload);
        });

        $this->assertEquals('Webhook Handled', $response->getContent());
    }

    public function test_normal_response_is_returned_if_method_is_missing()
    {
        $request = $this->request();

        Event::fake([
            RemitaWalletWebhookHandled::class,
            RemitaWalletWebhookReceived::class,
        ]);

        $response = (new WebhookControllerTestStub)->handleWebhook($request,'foo.bar');

        Event::assertDispatched(RemitaWalletWebhookReceived::class, function (RemitaWalletWebhookReceived $event) use ($request) {
            return $request->getContent() == json_encode($event->payload);
        });

        Event::assertNotDispatched(RemitaWalletWebhookHandled::class);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertSame('Missing event type: foo.bar', $response->getContent());
    }
    public function test_cannot_process_invalid_input()
    {
        $request = $this->requestWithInvalidInput();

        Event::fake([
            RemitaWalletWebhookHandled::class,
            RemitaWalletWebhookReceived::class,
        ]);

        $response = (new WebhookControllerTestStub)->handleWebhook($request,'test.succeeded');

        

        $this->assertEquals('Invalid input', $response->getContent());
    }
    private function request()
    {
        return Request::create(
            '/', 'POST', [], [], [], [], json_encode([])
        );
    }
    private function requestWithInvalidInput()
    {
        return Request::create(
            '/', 'POST', [], [], [], [], 'invalid input'
        );
    }
}

class WebhookControllerTestStub extends RemitaWalletWebhookController
{
    public function __construct()
    {
        // Don't call parent constructor to prevent setting middleware...
    }

    public function handleTestSucceeded()
    {
        return new Response('Webhook Handled', 200);
    }

    public function missingMethod($parameters = [],$event_type='unknown_event')
    {
        return new Response('Missing event type: '.$event_type);
    }
}