<?php

declare(strict_types=1);

namespace App\Controller;

use App\Core\Http\Request;
use App\Core\Http\Response;
use App\Exception\ValidationException;
use App\Kernel;
use App\Service\AuthService;

/** Kayıt, giriş, çıkış ve gizli soruyla şifre sıfırlama ekranları. İş kuralları AuthService'te. */
final class AuthController
{
    public function __construct(private readonly Kernel $kernel, private readonly AuthService $auth)
    {
    }

    public function loginForm(): Response
    {
        return $this->kernel->render('auth/login', ['title' => 'Giriş', 'old' => []]);
    }

    public function login(Request $request): Response
    {
        $customerNo = $request->string('customer_no');
        $user = $this->auth->login($customerNo, (string) $request->input('password', ''));

        if ($user === null) {
            return $this->kernel->render('auth/login', [
                'title' => 'Giriş',
                'error' => 'Müşteri numarası veya şifre hatalı.',
                'old' => ['customer_no' => $customerNo],
            ], 422);
        }

        $this->kernel->logIn($user);

        return Response::redirect('/');
    }

    public function logout(): Response
    {
        $this->kernel->logOut();
        $this->kernel->session()->flash('success', 'Çıkış yaptınız.');

        return Response::redirect('/giris');
    }

    public function registerForm(): Response
    {
        return $this->kernel->render('auth/register', $this->registerData([], []));
    }

    public function register(Request $request): Response
    {
        $old = [
            'name' => $request->string('name'),
            'surname' => $request->string('surname'),
            'secret_question' => $request->string('secret_question'),
            'secret_answer' => $request->string('secret_answer'),
        ];
        $password = (string) $request->input('password', '');

        if ($password !== (string) $request->input('password_confirm', '')) {
            return $this->kernel->render('auth/register', $this->registerData($old, ['password_confirm' => 'Şifreler aynı değil.']), 422);
        }

        try {
            $user = $this->auth->register($old['name'], $old['surname'], $password, $old['secret_question'], $old['secret_answer']);
        } catch (ValidationException $e) {
            return $this->kernel->render('auth/register', $this->registerData($old, $e->errors()), 422);
        }

        $this->kernel->session()->flash('new_customer_no', $user->customerNo);

        return Response::redirect('/kayit/tamam');
    }

    public function registerDone(): Response
    {
        $number = $this->kernel->session()->pullFlash('new_customer_no');
        if (!is_string($number)) {
            return Response::redirect('/giris');
        }

        return $this->kernel->render('auth/register_done', ['title' => 'Kayıt tamamlandı', 'customerNo' => $number]);
    }

    public function forgotForm(): Response
    {
        return $this->kernel->render('auth/forgot', ['title' => 'Şifremi unuttum', 'old' => []]);
    }

    /** 1. adım: müşteri numarasına göre gizli soruyu gösterir. */
    public function forgot(Request $request): Response
    {
        $customerNo = $request->string('customer_no');
        $question = $this->auth->secretQuestionFor($customerNo);

        if ($question === null) {
            return $this->kernel->render('auth/forgot', [
                'title' => 'Şifremi unuttum',
                'error' => 'Bu müşteri numarası bulunamadı.',
                'old' => ['customer_no' => $customerNo],
            ], 422);
        }

        return $this->kernel->render('auth/reset', ['title' => 'Yeni şifre', 'customerNo' => $customerNo, 'question' => $question, 'errors' => []]);
    }

    /** 2. adım: cevap doğruysa yeni şifreyi kaydeder. */
    public function reset(Request $request): Response
    {
        $customerNo = $request->string('customer_no');
        $question = $this->auth->secretQuestionFor($customerNo);
        if ($question === null) {
            return Response::redirect('/sifre-sifirla');
        }

        $errors = [];
        try {
            if ($this->auth->resetPassword($customerNo, $request->string('secret_answer'), (string) $request->input('password', ''))) {
                $this->kernel->session()->flash('success', 'Şifreniz değiştirildi. Yeni şifrenizle giriş yapabilirsiniz.');

                return Response::redirect('/giris');
            }
            $errors['secret_answer'] = 'Gizli sorunun cevabı hatalı.';
        } catch (ValidationException $e) {
            $errors = $e->errors();
        }

        return $this->kernel->render('auth/reset', ['title' => 'Yeni şifre', 'customerNo' => $customerNo, 'question' => $question, 'errors' => $errors], 422);
    }

    /**
     * @param array<string, string> $old
     * @param array<string, string> $errors
     * @return array<string, mixed>
     */
    private function registerData(array $old, array $errors): array
    {
        return ['title' => 'Kayıt ol', 'old' => $old, 'errors' => $errors, 'questions' => AuthService::SECRET_QUESTIONS];
    }
}
