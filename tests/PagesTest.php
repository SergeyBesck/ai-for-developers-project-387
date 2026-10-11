<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class PagesTest extends TestCase
{
    private static function page(string $file): string
    {
        $path = dirname(__DIR__) . '/public/' . $file;
        self::assertFileExists($path);

        $content = file_get_contents($path);
        self::assertIsString($content);

        return $content;
    }

    public function testHomePageFollowsTheReference(): void
    {
        $html = self::page('index.html');

        self::assertStringContainsString('<html lang="ru">', $html);
        self::assertStringContainsString('<title>Calendar', $html);
        self::assertStringContainsString('<h1>Calendar</h1>', $html);
        self::assertStringContainsString('Быстрая запись на звонок', $html);
        self::assertStringContainsString(
            'Забронируйте встречу за минуту: выберите тип события и удобное время.',
            $html,
        );
        self::assertStringContainsString('Возможности', $html);
        self::assertStringContainsString(
            'Выбор типа события и удобного времени для встречи.',
            $html,
        );
        self::assertStringContainsString(
            'Быстрое бронирование с подтверждением и дополнительными заметками.',
            $html,
        );
        self::assertStringContainsString(
            'Управление типами встреч и просмотр предстоящих записей в админке.',
            $html,
        );
    }

    public function testHomePageLeadsToBookingPage(): void
    {
        $html = self::page('index.html');

        self::assertStringContainsString('href="booking.html"', $html);
        self::assertStringContainsString('Записаться', $html);
    }

    public function testHomePageHasNoHealthCheckWidget(): void
    {
        self::assertStringNotContainsString('id="status"', self::page('index.html'));
        self::assertStringNotContainsString('app.js', self::page('index.html'));
    }

    public function testBookingPageIsAFunnelWithSteps(): void
    {
        $html = self::page('booking.html');

        self::assertStringContainsString('<html lang="ru">', $html);
        self::assertStringContainsString('href="index.html"', $html);
        self::assertStringContainsString('На главную', $html);
        self::assertStringContainsString('Выберите тип события', $html);
        self::assertStringContainsString('Выберите время', $html);
        self::assertStringContainsString('Нажмите на карточку, чтобы открыть календарь и выбрать удобный слот.', $html);
        self::assertStringContainsString('class="stepper"', $html);
        self::assertStringContainsString('>Тип<', $html);
        self::assertStringContainsString('>Время<', $html);
        self::assertStringContainsString('>Данные<', $html);
        self::assertStringContainsString('>Подтверждение<', $html);
        self::assertStringContainsString('aria-current="step"', $html);
    }

    public function testBookingPageHasNoStaticEventTypes(): void
    {
        $html = self::page('booking.html');

        self::assertStringNotContainsString('Встреча 15 минут', $html);
        self::assertStringNotContainsString('Встреча 30 минут', $html);
        self::assertStringNotContainsString('Tota', $html);
    }

    public function testBookingPageHasCalendarControlsAndTimezoneNote(): void
    {
        $html = self::page('booking.html');

        self::assertStringContainsString('id="calendar"', $html);
        self::assertStringContainsString('id="days"', $html);
        self::assertStringContainsString('id="prev-days"', $html);
        self::assertStringContainsString('id="next-days"', $html);
        self::assertStringContainsString('id="window-range"', $html);
        self::assertStringContainsString('id="timezone-note"', $html);
    }

    public function testEveryPageLinksToAdmin(): void
    {
        foreach (['index.html', 'booking.html', 'admin.html'] as $file) {
            $html = self::page($file);

            self::assertStringContainsString('href="admin.html"', $html, $file);
            self::assertStringContainsString('>Админка<', $html, $file);
        }
    }

    public function testAdminPageHasEventTypesTabAndEmptyState(): void
    {
        $html = self::page('admin.html');

        self::assertStringContainsString('<html lang="ru">', $html);
        self::assertStringContainsString('href="index.html"', $html);
        self::assertStringContainsString('href="booking.html"', $html);
        self::assertStringContainsString('Типы событий', $html);
        self::assertStringContainsString('Типов событий ещё нет', $html);
        self::assertStringContainsString('Создать тип события', $html);
        self::assertStringContainsString('<dialog', $html);
        self::assertStringContainsString('aria-current="page"', $html);
        self::assertStringContainsString('assets/admin.js', $html);
    }

    public function testAdminPageUsesGeneratedSdk(): void
    {
        $js = self::page('assets/admin.js');

        self::assertStringContainsString("from './sdk.gen.js'", $js);
        self::assertStringContainsString('listEventTypes', $js);
        self::assertStringContainsString('createEventType', $js);
        self::assertStringContainsString('DUPLICATE_MESSAGE', $js);
    }

    public function testAdminPageHasBookingsTabWithStatesAndPrivacyNote(): void
    {
        $html = self::page('admin.html');

        self::assertStringContainsString('id="tab-bookings"', $html);
        self::assertStringContainsString('id="panel-bookings"', $html);
        self::assertStringContainsString('Предстоящие записи', $html);
        self::assertStringContainsString('Предстоящих записей нет', $html);
        self::assertStringContainsString('id="bookings-loading"', $html);
        self::assertStringContainsString('id="bookings-error"', $html);
        // Видимость email Гостей в открытой админке сказано текстом, а не спрятано.
        self::assertStringContainsString('email Гостей видит любой', $html);
    }

    public function testAdminScriptListsBookingsThroughGeneratedSdkWithoutManualFetch(): void
    {
        $js = self::page('assets/admin.js');

        self::assertStringContainsString('listBookings', $js);
        self::assertStringContainsString("from './sdk.gen.js'", $js);
        self::assertStringContainsString('timeZone: timezone', $js);
        self::assertStringContainsString('textContent', $js);
        self::assertStringNotContainsString('fetch(', $js);
        self::assertStringNotContainsString('innerHTML', $js);
    }

    public function testBookingScriptUsesGeneratedSdkWithoutManualFetch(): void
    {
        $js = self::page('assets/booking.js');

        self::assertStringContainsString("from './sdk.gen.js'", $js);
        self::assertStringContainsString('listEventTypes', $js);
        self::assertStringContainsString('listSlots', $js);
        self::assertStringContainsString('textContent', $js);
        self::assertStringNotContainsString('fetch(', $js);
        self::assertStringNotContainsString('innerHTML', $js);
    }

    public function testBookingPageHasDataAndConfirmSteps(): void
    {
        $html = self::page('booking.html');

        self::assertStringContainsString('id="continue-to-data"', $html);
        self::assertStringContainsString('>Продолжить</button>', $html);
        self::assertStringContainsString('id="data-step"', $html);
        self::assertStringContainsString('id="booking-form"', $html);
        self::assertStringContainsString('id="guest-name"', $html);
        self::assertStringContainsString('id="guest-email"', $html);
        self::assertStringContainsString('id="guest-note"', $html);
        self::assertStringContainsString('maxlength="100"', $html);
        self::assertStringContainsString('maxlength="500"', $html);
        self::assertStringContainsString('id="confirm-step"', $html);
        self::assertStringContainsString('id="confirm-type"', $html);
        self::assertStringContainsString('id="confirm-when"', $html);
        self::assertStringContainsString('id="confirm-name"', $html);
        self::assertStringContainsString('id="confirm-email"', $html);
        self::assertStringContainsString('id="confirm-note"', $html);
        self::assertStringContainsString('href="index.html">На главную</a>', $html);
    }

    public function testBookingScriptCreatesBookingThroughGeneratedSdk(): void
    {
        $js = self::page('assets/booking.js');

        self::assertStringContainsString('createBooking', $js);
        self::assertStringContainsString("from './sdk.gen.js'", $js);
        self::assertStringNotContainsString('fetch(', $js);
        self::assertStringNotContainsString('innerHTML', $js);
    }

    public function testBookingScriptPuts400ErrorsOnFieldsAndReturnsToTimeOnConflict(): void
    {
        $js = self::page('assets/booking.js');

        self::assertStringContainsString('guest-name-error', $js);
        self::assertStringContainsString('guest-email-error', $js);
        self::assertStringContainsString('guest-note-error', $js);
        self::assertStringContainsString('applyFieldErrors', $js);
        self::assertStringContainsString('Время уже заняли, выберите другое.', $js);
        self::assertStringContainsString('status === 409', $js);
    }
}
