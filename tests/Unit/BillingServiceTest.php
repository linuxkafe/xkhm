<?php

declare(strict_types=1);

namespace Xkhm\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Xkhm\Service\BillingService;
use Xkhm\Repository\InvoiceRepositoryInterface;
use Xkhm\Repository\OrderRepositoryInterface;
use Xkhm\Repository\ClientRepositoryInterface;

class BillingServiceTest extends TestCase
{
    private BillingService $service;

    /** @var \PHPUnit\Framework\MockObject\MockObject&InvoiceRepositoryInterface */
    private $invoiceRepo;

    /** @var \PHPUnit\Framework\MockObject\MockObject&OrderRepositoryInterface */
    private $orderRepo;

    /** @var \PHPUnit\Framework\MockObject\MockObject&ClientRepositoryInterface */
    private $clientRepo;

    protected function setUp(): void
    {
        $this->invoiceRepo = $this->createMock(InvoiceRepositoryInterface::class);
        $this->orderRepo = $this->createMock(OrderRepositoryInterface::class);
        $this->clientRepo = $this->createMock(ClientRepositoryInterface::class);
        $this->service = new BillingService($this->invoiceRepo, $this->orderRepo, $this->clientRepo);
    }

    public function testCreateInvoiceFromOrder(): void
    {
        $order = ['id' => 1, 'userid' => 5, 'total' => 100.00, 'currency' => 1, 'tax' => 0];
        $this->orderRepo->method('findById')->with(1)->willReturn($order);
        $this->invoiceRepo->method('create')->willReturn(10);

        $invoiceId = $this->service->createInvoiceFromOrder(1);

        $this->assertEquals(10, $invoiceId);
    }

    public function testCreateInvoiceFromOrderNotFound(): void
    {
        $this->orderRepo->method('findById')->willReturn(null);

        $invoiceId = $this->service->createInvoiceFromOrder(999);

        $this->assertNull($invoiceId);
    }

    public function testGetClientInvoices(): void
    {
        $expected = [['id' => 1, 'total' => 100], ['id' => 2, 'total' => 50]];
        $this->invoiceRepo->method('findByUserId')->with(5)->willReturn($expected);

        $invoices = $this->service->getClientInvoices(5);

        $this->assertCount(2, $invoices);
    }

    public function testCalculateTotal(): void
    {
        $invoice = [
            'id' => 1,
            'items' => [
                ['description' => 'Item 1', 'amount' => 50.00],
                ['description' => 'Item 2', 'amount' => 25.50],
            ],
        ];
        $this->invoiceRepo->method('findByIdWithItems')->willReturn($invoice);

        $total = $this->service->calculateTotal(1);

        $this->assertEquals(75.50, $total);
    }

    public function testIsOverdue(): void
    {
        $invoice = [
            'id' => 1,
            'status' => 'Unpaid',
            'duedate' => date('Y-m-d', strtotime('-1 day')),
            'items' => [],
        ];
        $this->invoiceRepo->method('findByIdWithItems')->willReturn($invoice);

        $this->assertTrue($this->service->isOverdue(1));
    }

    public function testIsNotOverdue(): void
    {
        $invoice = [
            'id' => 1,
            'status' => 'Paid',
            'duedate' => date('Y-m-d', strtotime('+1 day')),
            'items' => [],
        ];
        $this->invoiceRepo->method('findByIdWithItems')->willReturn($invoice);

        $this->assertFalse($this->service->isOverdue(1));
    }

    public function testGetInvoiceSummary(): void
    {
        $invoices = [
            ['id' => 1, 'status' => 'Paid', 'total' => 100],
            ['id' => 2, 'status' => 'Unpaid', 'total' => 50],
        ];
        $this->invoiceRepo->method('findByUserId')->willReturn($invoices);

        $client = ['id' => 5, 'credit' => 10.00];
        $this->clientRepo->method('findById')->willReturn($client);

        $summary = $this->service->getInvoiceSummary(5);

        $this->assertEquals(10.00, $summary['credit_balance']);
        $this->assertEquals(50.00, $summary['unpaid_total']);
        $this->assertEquals(100.00, $summary['paid_total']);
    }
}
