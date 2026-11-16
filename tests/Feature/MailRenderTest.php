<?php

namespace Tests\Feature;

use App\Mail\ResetPassword;
use App\Mail\Welcome;
use App\Models\User;
use Tests\TestCase;

class MailRenderTest extends TestCase
{
    public static function languages(): array
    {
        return [['en'], ['nl']];
    }

    /** @dataProvider languages */
    public function test_welcome_mail_renders(string $language): void
    {
        $user = User::factory()->make(['name' => 'Sam', 'language' => $language]);
        $mail = new Welcome('https://example.com/activate', $user);

        $mail->assertHasSubject($language === 'nl' ? 'Welkom bij Shoplisto!' : 'Welcome to Shoplisto!');
        $mail->assertSeeInHtml('https://example.com/activate');
        $mail->assertSeeInHtml('Sam');
        $mail->assertSeeInHtml('/information');
        $mail->assertSeeInHtml('/android');
        $mail->assertDontSeeInHtml('Safari');
        $mail->assertDontSeeInHtml('welcome.');
        $mail->assertSeeInText('https://example.com/activate');
        $mail->assertDontSeeInText('<table', false);
        $mail->assertDontSeeInText('welcome.');
    }

    /** @dataProvider languages */
    public function test_reset_password_mail_renders(string $language): void
    {
        $user = User::factory()->make(['name' => 'Sam', 'language' => $language]);
        $mail = new ResetPassword('https://example.com/reset', $user);

        $mail->assertSeeInHtml('https://example.com/reset');
        $mail->assertSeeInHtml('Sam');
        $mail->assertDontSeeInHtml('password-reset.');
        $mail->assertSeeInText('https://example.com/reset');
        $mail->assertDontSeeInText('<table', false);
    }

    /** @dataProvider languages */
    public function test_every_mail_says_whose_app_it_is_and_where_to_ask(string $language): void
    {
        config(['app.url' => 'https://www.tomjielis.com', 'support.address' => 'support@tomjielis.com']);
        $user = User::factory()->make(['name' => 'Sam', 'language' => $language]);
        $line = $language === 'nl' ? 'Shoplisto is een app van' : 'Shoplisto is an app by';

        foreach ([new Welcome('https://example.com/activate', $user), new ResetPassword('https://example.com/reset', $user)] as $mail) {
            $mail->assertSeeInHtml($line);
            $mail->assertSeeInHtml('>tomjielis.com</a>', false);
            $mail->assertSeeInHtml('mailto:support@tomjielis.com', false);
            $mail->assertSeeInText($line . ' tomjielis.com');
            $mail->assertSeeInText('support@tomjielis.com');
            $mail->assertDontSeeInText('mail.sender');
        }
    }
}
