<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Model\Enum\Role;
use App\Model\User;

/** US-28 alt kategoriler; FR-37 form alanı türleri (seçim, e-posta) ve en fazla uzunluk kuralı. */
final class SubcategoryAndFieldTypesTest extends FeatureTestCase
{
    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedCatalog();
        $this->admin = $this->kernel->authService()->createStaff(Role::Admin, 'Selin', 'Arslan', self::PASSWORD);
        $this->loginAs($this->admin->customerNo, self::PASSWORD);
    }

    private function asCustomer(): void
    {
        $this->post('/cikis');
        $this->loginAs($this->registerCustomer(), self::PASSWORD);
    }

    public function test_subcategory_is_created_under_parent_and_listed_with_path(): void
    {
        self::assertSame(302, $this->post('/yonetici/kategoriler', ['name' => 'Yazıcı', 'parent_id' => '1'])->status);
        $sub = $this->kernel->categoryRepository()->findById(5);
        self::assertSame(1, $sub?->parentId);

        $nested = $this->post('/yonetici/kategoriler', ['name' => 'Lazer', 'parent_id' => '5']);
        self::assertSame(422, $nested->status);
        self::assertStringContainsString('Alt kategori yalnız ana kategorinin altına eklenebilir.', $nested->body);

        $this->asCustomer();
        $form = $this->get('/talepler/yeni')->body;
        self::assertStringContainsString('Donanım › Yazıcı', $form);
        $this->post('/talepler', ['category_id' => '5', 'priority' => '2', 'title' => 'Yazıcı arızası', 'description' => 'Yazıcı kağıt sıkıştırıyor.']);
        self::assertStringContainsString('Donanım › Yazıcı', $this->get('/talepler/1')->body);
    }

    public function test_select_email_and_max_length_fields_are_validated(): void
    {
        self::assertSame(302, $this->post('/yonetici/kategoriler/1/alanlar', ['label' => 'Cihaz türü', 'type' => 'select', 'options' => 'Masaüstü, Dizüstü , Yazıcı', 'required' => '1'])->status);
        self::assertSame(302, $this->post('/yonetici/kategoriler/1/alanlar', ['label' => 'İletişim e-postası', 'type' => 'email', 'required' => ''])->status);
        self::assertSame(302, $this->post('/yonetici/kategoriler/1/alanlar', ['label' => 'Seri no', 'type' => 'text', 'max_length' => '5', 'required' => ''])->status);
        $noOptions = $this->post('/yonetici/kategoriler/1/alanlar', ['label' => 'Boş seçim', 'type' => 'select', 'options' => ' , ', 'required' => '']);
        self::assertSame(422, $noOptions->status);
        self::assertStringContainsString('Seçim alanı için en az bir seçenek yazın.', $noOptions->body);
        [$select, $email, $serial] = $this->kernel->categoryFieldRepository()->forCategory(1);

        $this->asCustomer();
        self::assertStringContainsString('<option value="Dizüstü">Dizüstü</option>', $this->get('/talepler/yeni')->body);
        $bad = $this->post('/talepler', [
            'category_id' => '1', 'priority' => '2', 'title' => 'Yazıcı arızası', 'description' => 'Yazıcı kağıt sıkıştırıyor.',
            'extra' => [$select['id'] => 'Tablet', $email['id'] => 'yanlis-adres', $serial['id'] => 'ABCDEF'],
        ]);
        self::assertSame(422, $bad->status);
        self::assertStringContainsString('Cihaz türü için listeden bir seçenek seçin.', $bad->body);
        self::assertStringContainsString('İletişim e-postası geçerli bir e-posta adresi olmalı.', $bad->body);
        self::assertStringContainsString('Seri no en fazla 5 karakter olabilir.', $bad->body);

        $this->post('/talepler', [
            'category_id' => '1', 'priority' => '2', 'title' => 'Yazıcı arızası', 'description' => 'Yazıcı kağıt sıkıştırıyor.',
            'extra' => [$select['id'] => 'Dizüstü', $email['id'] => 'ali@ornek.com', $serial['id'] => 'AB12'],
        ]);
        self::assertSame(['Cihaz türü' => 'Dizüstü', 'İletişim e-postası' => 'ali@ornek.com', 'Seri no' => 'AB12'], $this->kernel->ticketRepository()->findById(1)?->extraFields);
    }
}
