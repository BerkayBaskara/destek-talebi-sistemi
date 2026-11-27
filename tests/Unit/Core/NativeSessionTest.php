<?php

declare(strict_types=1);

namespace Tests\Unit\Core;

use App\Core\Session\NativeSession;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;

/** AUTH-015…018: gerçek PHP oturumuyla kaydetme, okuma, kimlik yenileme ve sonlandırma (ayrı süreçte). */
final class NativeSessionTest extends TestCase
{
    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function test_set_get_regenerate_and_destroy(): void
    {
        ini_set('session.save_path', sys_get_temp_dir());
        $session = new NativeSession();

        $session->set('user_id', 7);
        self::assertSame(7, $session->get('user_id'));
        self::assertSame('yok', $session->get('olmayan', 'yok'));

        $session->flash('success', 'Kaydedildi');
        self::assertSame('Kaydedildi', $session->pullFlash('success'));
        self::assertNull($session->pullFlash('success'), 'Flash bir kez okunur.');

        $before = session_id();
        $session->regenerate();
        self::assertNotSame($before, session_id(), 'Oturum kimliği yenilenir.');
        self::assertSame(7, $session->get('user_id'), 'Yenilemede veri korunur.');

        $id = session_id();
        $session->destroy();
        self::assertNull($session->get('user_id'), 'Sonlandırmada veri silinir.');
        self::assertNotSame($id, session_id());
    }
}
