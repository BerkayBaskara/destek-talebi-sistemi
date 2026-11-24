<?php

declare(strict_types=1);

use App\Controller\AdminController;
use App\Controller\AgentController;
use App\Controller\AttachmentController;
use App\Controller\KanbanController;
use App\Controller\AuthController;
use App\Controller\CalendarController;
use App\Controller\CatalogController;
use App\Controller\HierarchyController;
use App\Controller\NotificationController;
use App\Controller\ReportController;
use App\Controller\SettingsController;
use App\Controller\StaffController;
use App\Controller\CustomerController;
use App\Controller\HomeController;
use App\Controller\TicketController;
use App\Core\Http\Request;
use App\Core\Http\Response;
use App\Core\Http\Router;
use App\Kernel;
use App\Service\ReportService;

/**
 * Rota tablosu: hangi adres hangi controller metoduna gider.
 */
return static function (Router $router, Kernel $kernel): void {
    $home = new HomeController($kernel);
    $auth = new AuthController($kernel, $kernel->authService());

    $router->add('GET', '/', static fn (Request $r, array $p): Response => $home->index());

    // Kimlik doğrulama
    $router->add('GET', '/giris', static fn (Request $r, array $p): Response => $auth->loginForm());
    $router->add('POST', '/giris', static fn (Request $r, array $p): Response => $auth->login($r));
    $router->add('POST', '/cikis', static fn (Request $r, array $p): Response => $auth->logout());
    $router->add('GET', '/kayit', static fn (Request $r, array $p): Response => $auth->registerForm());
    $router->add('POST', '/kayit', static fn (Request $r, array $p): Response => $auth->register($r));
    $router->add('GET', '/kayit/tamam', static fn (Request $r, array $p): Response => $auth->registerDone());
    $router->add('GET', '/sifre-sifirla', static fn (Request $r, array $p): Response => $auth->forgotForm());
    $router->add('POST', '/sifre-sifirla', static fn (Request $r, array $p): Response => $auth->forgot($r));
    $router->add('POST', '/sifre-sifirla/yeni', static fn (Request $r, array $p): Response => $auth->reset($r));

    // Talepler
    $tickets = new TicketController($kernel, $kernel->ticketService());
    $router->add('GET', '/talepler', static fn (Request $r, array $p): Response => $tickets->index($r));
    $router->add('GET', '/talepler/yeni', static fn (Request $r, array $p): Response => $tickets->createForm());
    $router->add('POST', '/talepler', static fn (Request $r, array $p): Response => $tickets->store($r));
    $router->add('GET', '/talepler/{id}', static fn (Request $r, array $p): Response => $tickets->show((int) $p['id']));
    $router->add('GET', '/talepler/{id}/olusturuldu', static fn (Request $r, array $p): Response => $tickets->created((int) $p['id']));
    $router->add('POST', '/talepler/{id}/yanit', static fn (Request $r, array $p): Response => $tickets->reply($r, (int) $p['id']));
    $router->add('POST', '/talepler/{id}/islem', static fn (Request $r, array $p): Response => $tickets->action($r, (int) $p['id']));
    $router->add('POST', '/talepler/{id}/karar', static fn (Request $r, array $p): Response => $tickets->decide($r, (int) $p['id']));
    $router->add('POST', '/talepler/{id}/eskale', static fn (Request $r, array $p): Response => $tickets->escalate($r, (int) $p['id']));
    $router->add('POST', '/talepler/{id}/ata', static fn (Request $r, array $p): Response => $tickets->assign($r, (int) $p['id']));
    $router->add('POST', '/talepler/{id}/cozulemedi', static fn (Request $r, array $p): Response => $tickets->closeUnresolvable($r, (int) $p['id']));

    // Bildirimler (FR-18)
    $notifications = new NotificationController($kernel);
    $router->add('GET', '/bildirimler', static fn (Request $r, array $p): Response => $notifications->index($r));
    $router->add('POST', '/bildirimler/okundu', static fn (Request $r, array $p): Response => $notifications->markAllRead());
    $router->add('GET', '/bildirimler/{id}', static fn (Request $r, array $p): Response => $notifications->open((int) $p['id']));
    $router->add('POST', '/bildirimler/{id}/okunmadi', static fn (Request $r, array $p): Response => $notifications->markUnread((int) $p['id']));
    $router->add('GET', '/api/bildirimler/sayi', static fn (Request $r, array $p): Response => $notifications->count());

    // Ek dosyalar (yalnız yetkili kullanıcıya)
    $attachments = new AttachmentController($kernel, $kernel->attachmentService());
    $router->add('GET', '/ekler/{id}', static fn (Request $r, array $p): Response => $attachments->download((int) $p['id']));

    // Personel: Görev Havuzu, Görevlerim, Kanban (AJAX)
    $agent = new AgentController($kernel, $kernel->ticketService());
    $kanban = new KanbanController($kernel, $kernel->ticketService());
    $router->add('GET', '/havuz', static fn (Request $r, array $p): Response => $agent->pool());
    $router->add('POST', '/havuz/{id}/al', static fn (Request $r, array $p): Response => $agent->take((int) $p['id']));
    $router->add('GET', '/personel/talepler', static fn (Request $r, array $p): Response => $agent->myTickets());
    $router->add('GET', '/kanban', static fn (Request $r, array $p): Response => $kanban->board());
    $router->add('POST', '/api/talepler/{id}/durum', static fn (Request $r, array $p): Response => $kanban->move($r, (int) $p['id']));
    $router->add('GET', '/api/sla', static fn (Request $r, array $p): Response => $kanban->sla($r));

    // Yönetici: tüm talepler ve Aşama 2 özet raporu
    $admin = new AdminController($kernel, $kernel->ticketService(), new ReportService(
        $kernel->ticketRepository(),
        $kernel->userRepository(),
        $kernel->slaStatusService(),
        $kernel->slaCalculator(),
        $kernel->priorityRepository(),
    ));
    $router->add('GET', '/yonetici', static fn (Request $r, array $p): Response => $admin->tickets($r));
    $router->add('GET', '/yonetici/ozet', static fn (Request $r, array $p): Response => $admin->summary());

    // Yönetici: Sistem Yapılandırması (site, kategori, öncelik, SLA, takvim, personel, denetim kaydı)
    $settings = new SettingsController($kernel);
    $router->add('GET', '/yonetici/ayarlar', static fn (Request $r, array $p): Response => $settings->index());
    $router->add('GET', '/yonetici/ayarlar/site', static fn (Request $r, array $p): Response => $settings->siteForm());
    $router->add('POST', '/yonetici/ayarlar/site', static fn (Request $r, array $p): Response => $settings->saveSite($r));
    $router->add('POST', '/yonetici/ayarlar/logo', static fn (Request $r, array $p): Response => $settings->uploadLogo($r));
    $router->add('POST', '/yonetici/ayarlar/dosya', static fn (Request $r, array $p): Response => $settings->saveFiles($r));
    $router->add('POST', '/yonetici/ayarlar/sorular', static fn (Request $r, array $p): Response => $settings->saveQuestions($r));
    $router->add('GET', '/yonetici/ayarlar/guvenlik', static fn (Request $r, array $p): Response => $settings->securityPage());
    $router->add('POST', '/yonetici/ayarlar/guvenlik', static fn (Request $r, array $p): Response => $settings->saveSecurity($r));
    $router->add('POST', '/yonetici/ayarlar/bildirimler', static fn (Request $r, array $p): Response => $settings->saveNotifications($r));
    $router->add('POST', '/yonetici/ayarlar/ana-gorsel', static fn (Request $r, array $p): Response => $settings->uploadHomeImage($r));
    $router->add('POST', '/yonetici/ayarlar/ana-gorsel/kaldir', static fn (Request $r, array $p): Response => $settings->removeHomeImage());
    $router->add('GET', '/yonetici/denetim', static fn (Request $r, array $p): Response => $settings->audit());
    $router->add('GET', '/yonetici/yedek', static fn (Request $r, array $p): Response => $settings->backupPage());
    $router->add('POST', '/yonetici/yedek/indir', static fn (Request $r, array $p): Response => $settings->backupDownload());
    $router->add('POST', '/yonetici/yedek/geri-yukle', static fn (Request $r, array $p): Response => $settings->restore($r));

    $report = new ReportController($kernel);
    $router->add('GET', '/yonetici/rapor', static fn (Request $r, array $p): Response => $report->page($r));
    $router->add('GET', '/yonetici/rapor.csv', static fn (Request $r, array $p): Response => $report->csv($r));

    $catalog = new CatalogController($kernel);
    $router->add('GET', '/yonetici/kategoriler', static fn (Request $r, array $p): Response => $catalog->categories());
    $router->add('POST', '/yonetici/kategoriler', static fn (Request $r, array $p): Response => $catalog->createCategory($r));
    $router->add('POST', '/yonetici/kategoriler/{id}', static fn (Request $r, array $p): Response => $catalog->updateCategory($r, (int) $p['id']));
    $router->add('POST', '/yonetici/kategoriler/{id}/alanlar', static fn (Request $r, array $p): Response => $catalog->addField($r, (int) $p['id']));
    $router->add('POST', '/yonetici/kategoriler/alanlar/{id}/sil', static fn (Request $r, array $p): Response => $catalog->removeField((int) $p['id']));
    $router->add('GET', '/yonetici/oncelikler', static fn (Request $r, array $p): Response => $catalog->priorities());
    $router->add('POST', '/yonetici/oncelikler', static fn (Request $r, array $p): Response => $catalog->createPriority($r));
    $router->add('POST', '/yonetici/oncelikler/{id}', static fn (Request $r, array $p): Response => $catalog->updatePriority($r, (int) $p['id']));
    $router->add('GET', '/yonetici/sla', static fn (Request $r, array $p): Response => $catalog->sla());
    $router->add('POST', '/yonetici/sla', static fn (Request $r, array $p): Response => $catalog->saveSla($r));
    $router->add('POST', '/yonetici/sla/kurallar', static fn (Request $r, array $p): Response => $catalog->saveSlaRules($r));

    $calendar = new CalendarController($kernel);
    $router->add('GET', '/yonetici/takvim', static fn (Request $r, array $p): Response => $calendar->index($r));
    $router->add('POST', '/yonetici/takvim/ozel-gun', static fn (Request $r, array $p): Response => $calendar->addSpecialDay($r));
    $router->add('POST', '/yonetici/takvim/ozel-gun/sil', static fn (Request $r, array $p): Response => $calendar->removeSpecialDay($r));
    $router->add('POST', '/yonetici/takvim/yukle', static fn (Request $r, array $p): Response => $calendar->import($r));
    $router->add('POST', '/yonetici/takvim/kural', static fn (Request $r, array $p): Response => $calendar->saveRule($r));
    $router->add('POST', '/yonetici/takvim/mesai', static fn (Request $r, array $p): Response => $calendar->saveHours($r));
    $router->add('POST', '/yonetici/takvim/tatil', static fn (Request $r, array $p): Response => $calendar->addHoliday($r));
    $router->add('POST', '/yonetici/takvim/tatil/sil', static fn (Request $r, array $p): Response => $calendar->removeHoliday($r));

    $hierarchy = new HierarchyController($kernel);
    $router->add('GET', '/yonetici/hiyerarsi', static fn (Request $r, array $p): Response => $hierarchy->index());
    $router->add('POST', '/yonetici/hiyerarsi/{id}', static fn (Request $r, array $p): Response => $hierarchy->save($r, (int) $p['id']));
    $router->add('GET', '/yonetici/izinler', static fn (Request $r, array $p): Response => $hierarchy->leaves());
    $router->add('POST', '/yonetici/izinler', static fn (Request $r, array $p): Response => $hierarchy->addLeave($r));
    $router->add('POST', '/yonetici/izinler/{id}/sil', static fn (Request $r, array $p): Response => $hierarchy->removeLeave((int) $p['id']));

    $staff = new StaffController($kernel);
    $router->add('GET', '/yonetici/personel', static fn (Request $r, array $p): Response => $staff->index());
    $router->add('POST', '/yonetici/personel', static fn (Request $r, array $p): Response => $staff->create($r));
    $router->add('POST', '/yonetici/personel/{id}', static fn (Request $r, array $p): Response => $staff->update($r, (int) $p['id']));

    $customers = new CustomerController($kernel);
    $router->add('GET', '/yonetici/musteriler', static fn (Request $r, array $p): Response => $customers->index($r));
    $router->add('POST', '/yonetici/musteriler/{id}/durum', static fn (Request $r, array $p): Response => $customers->setActive($r, (int) $p['id']));
};
