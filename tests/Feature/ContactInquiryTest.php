<?php

namespace Tests\Feature;

use App\Models\ContactInquiry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ContactInquiryTest extends TestCase
{
    use RefreshDatabase;

    public function test_contact_inquiry_requires_privacy_consent(): void
    {
        $response = $this->postJson('/api/contact', [
            'type' => 'support',
            'name' => 'Jane Doe',
            'email' => 'jane@example.com',
            'message' => 'Hello',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['privacy_consent']);
    }

    public function test_contact_inquiry_is_stored_with_consent(): void
    {
        $response = $this->postJson('/api/contact', [
            'type' => 'enterprise',
            'name' => 'Jane Doe',
            'email' => 'jane@example.com',
            'subject' => 'Enterprise',
            'message' => 'We need unlimited stores.',
            'privacy_consent' => true,
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseCount('contact_inquiries', 1);
        $inquiry = ContactInquiry::first();
        $this->assertSame('enterprise', $inquiry->type);
        $this->assertNotNull($inquiry->privacy_consent_at);
    }
}
