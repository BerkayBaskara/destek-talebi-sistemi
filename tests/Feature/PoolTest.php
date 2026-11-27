<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Model\Enum\Role;
use App\Model\Enum\TicketStatus;
use App\Model\User;

/** Ortak havuz: personeli olmayan anda açılan talepler; personel "üzerime al" ile üstlenir (NFR-04). */
final class PoolTest extends FeatureTestCase
{
    private User $zeynep;
    private User $murat;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedCatalog();
        // Personel yokken talep açılır → havuzda kalır.
        $this->loginAs($this->registerCustomer(), self::PASSWORD);
        $this->post('/talepler', [
            'category_id' => '1', 'priority' => '2', 'title' => 'Havuzdaki yazıcı talebi',
            'description' => 'Yazıcı kağıt sıkıştırıyor, bakılması gerekiyor.',
        ]);
        $this->post('/cikis');

        $auth = $this->kernel->authService();
        $this->zeynep = $auth->createStaff(Role::Agent, 'Zeynep', 'Kaya', self::PASSWORD);
        $this->murat = $auth->createStaff(Role::Agent, 'Murat', 'Demir', self::PASSWORD);
    }

    public function test_pool_lists_unassigned_tickets(): void
    {
        $this->loginAs($this->zeynep->customerNo, self::PASSWORD);

        $body = $this->get('/havuz')->body;

        self::assertStringContainsString('Havuzdaki yazıcı talebi', $body);
        self::assertStringContainsString('Üzerime al', $body);
    }

    public function test_agent_takes_pool_ticket(): void
    {
        $this->loginAs($this->zeynep->customerNo, self::PASSWORD);

        $response = $this->post('/havuz/1/al');

        self::assertSame('/talepler/1', $response->headers['Location'] ?? null);
        $ticket = $this->kernel->ticketRepository()->findById(1);
        self::assertSame(TicketStatus::Taken, $ticket?->status);
        self::assertSame($this->zeynep->id, $ticket?->agentId);
        self::assertStringContainsString('Havuzdaki yazıcı talebi', $this->get('/personel/talepler')->body);
        self::assertStringNotContainsString('Havuzdaki yazıcı talebi', $this->get('/havuz')->body);
    }

    public function test_second_take_shows_conflict_message(): void
    {
        $this->loginAs($this->zeynep->customerNo, self::PASSWORD);
        $this->post('/havuz/1/al');
        $this->post('/cikis');

        $this->loginAs($this->murat->customerNo, self::PASSWORD);
        $response = $this->post('/havuz/1/al');

        self::assertSame('/havuz', $response->headers['Location'] ?? null);
        self::assertStringContainsString('Bu talep az önce başka bir personel tarafından alındı.', $this->get('/havuz')->body);
        self::assertSame($this->zeynep->id, $this->kernel->ticketRepository()->findById(1)?->agentId);
    }

    public function test_customer_cannot_see_pool_or_take(): void
    {
        $this->loginAs($this->registerCustomer('Ayşe'), self::PASSWORD);

        self::assertSame(403, $this->get('/havuz')->status);
        self::assertSame(403, $this->post('/havuz/1/al')->status);
        self::assertSame(403, $this->get('/personel/talepler')->status);
    }
}
