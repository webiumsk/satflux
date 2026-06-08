<?php

namespace Tests\Unit;

use App\Enums\ComplianceSubmissionStatus;
use App\Support\Invoicing\Efaktura\SapiSkDocumentStatusMapper;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SapiSkDocumentStatusMapperTest extends TestCase
{
    #[Test]
    public function test_maps_accepted_and_delivered_to_approved(): void
    {
        $this->assertSame(
            ComplianceSubmissionStatus::Approved,
            SapiSkDocumentStatusMapper::fromProviderStatus('ACCEPTED'),
        );
        $this->assertSame(
            ComplianceSubmissionStatus::Approved,
            SapiSkDocumentStatusMapper::fromProviderPayload(['status' => 'DELIVERED']),
        );
    }

    #[Test]
    public function test_maps_failed_and_rejected_distinctly(): void
    {
        $this->assertSame(
            ComplianceSubmissionStatus::Failed,
            SapiSkDocumentStatusMapper::fromProviderStatus('FAILED'),
        );
        $this->assertSame(
            ComplianceSubmissionStatus::Rejected,
            SapiSkDocumentStatusMapper::fromProviderStatus('REJECTED'),
        );
    }

    #[Test]
    public function test_maps_processing_to_submitted(): void
    {
        $this->assertSame(
            ComplianceSubmissionStatus::Submitted,
            SapiSkDocumentStatusMapper::fromProviderStatus('PROCESSING'),
        );
    }
}
