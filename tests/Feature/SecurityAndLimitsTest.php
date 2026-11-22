<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Model\Enum\Role;
use App\Model\User;

/** Dosya sınırları ayarı, güvenlik soruları yönetimi, kullanıcının kendi sorusu ve şifre sıfırlama deneme sınırı. */
final class SecurityAndLimitsTest extends FeatureTestCase
{
    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedCatalog();
        $this->admin = $this->kernel->authService()->createStaff(Role::Admin, 'Selin', 'Arslan', self::PASSWORD);
    }

    public function test_admin_changes_file_limits_and_ticket_form_follows(): void
    {
        $this->loginAs($this->admin->customerNo, self::PASSWORD);

        $bad = $this->post('/yonetici/ayarlar/dosya', ['max_image_mb' => '0', 'max_video_mb' => '500', 'max_count' => '3']);
        self::assertSame(422, $bad->status);
        self::assertStringContainsString('Görsel/PDF sınırı 1 ile 20 MB arasında olmalı.', $bad->body);
        self::assertStringContainsString('Video sınırı 1 ile 200 MB arasında olmalı.', $bad->body);

        self::assertSame(302, $this->post('/yonetici/ayarlar/dosya', ['max_image_mb' => '2', 'max_video_mb' => '20', 'max_count' => '2'])->status);
        $this->post('/cikis');

        $this->loginAs($this->registerCustomer(), self::PASSWORD);
        $form = $this->get('/talepler/yeni')->body;
        self::assertStringContainsString('en fazla 2 dosya; görsel/PDF 2 MB, video 20 MB', $form);
        self::assertSame(2, $this->kernel->attachmentService()->maxFiles());
    }

    public function test_admin_edits_security_questions_and_register_lists_them(): void
    {
        $this->loginAs($this->admin->customerNo, self::PASSWORD);

        $bad = $this->post('/yonetici/ayarlar/sorular', ['questions' => "  \n  "]);
        self::assertSame(422, $bad->status);
        self::assertStringContainsString('En az bir güvenlik sorusu olmalı', $bad->body);

        $this->post('/yonetici/ayarlar/sorular', ['questions' => "En sevdiğiniz renk nedir?\nİlk arabanızın markası nedir?"]);
        $this->post('/cikis');

        $register = $this->get('/kayit')->body;
        self::assertStringContainsString('En sevdiğiniz renk nedir?', $register);
        self::assertStringNotContainsString('Doğduğunuz şehir neresidir?', $register);
        self::assertStringContainsString('Kendi sorumu yazacağım', $register);
    }

    public function test_customer_registers_with_own_question_and_reset_shows_it(): void
    {
        $response = $this->post('/kayit', [
            'name' => 'Ayşe', 'surname' => 'Demir', 'password' => self::PASSWORD, 'password_confirm' => self::PASSWORD,
            'secret_question' => '__custom', 'custom_question' => 'Babaannemin köyü neresidir?', 'secret_answer' => 'Akçaabat',
        ]);
        self::assertSame('/kayit/tamam', $response->headers['Location'] ?? null);
        preg_match('/M\d{6}/', $this->get('/kayit/tamam')->body, $m);

        $reset = $this->post('/sifre-sifirla', ['customer_no' => $m[0]]);
        self::assertStringContainsString('Babaannemin köyü neresidir?', $reset->body);

        $short = $this->post('/kayit', [
            'name' => 'Can', 'surname' => 'Koç', 'password' => self::PASSWORD, 'password_confirm' => self::PASSWORD,
            'secret_question' => '__custom', 'custom_question' => 'Ne?', 'secret_answer' => 'Akçaabat',
        ]);
        self::assertSame(422, $short->status);
        self::assertStringContainsString('Kendi sorunuz 5-150 karakter olmalı.', $short->body);
    }

    public function test_reset_is_blocked_after_five_wrong_answers_for_fifteen_minutes(): void
    {
        $customerNo = $this->registerCustomer(); // cevap: Pamuk
        for ($i = 0; $i < 5; $i++) {
            $wrong = $this->post('/sifre-sifirla/yeni', ['customer_no' => $customerNo, 'secret_answer' => 'yanlış', 'password' => 'YeniSifre1!']);
            self::assertStringContainsString('Gizli sorunun cevabı hatalı.', $wrong->body);
        }

        $blocked = $this->post('/sifre-sifirla/yeni', ['customer_no' => $customerNo, 'secret_answer' => 'Pamuk', 'password' => 'YeniSifre1!']);
        self::assertSame(422, $blocked->status);
        self::assertStringContainsString('Çok fazla hatalı deneme yapıldı. 15 dakika sonra tekrar deneyin.', $blocked->body);

        $this->clock->advance('+16 minutes');
        $ok = $this->post('/sifre-sifirla/yeni', ['customer_no' => $customerNo, 'secret_answer' => 'Pamuk', 'password' => 'YeniSifre1!']);
        self::assertSame('/giris', $ok->headers['Location'] ?? null);
    }
}
