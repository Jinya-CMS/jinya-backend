<?php

namespace App\Mailing\Types;

use App\Mailing\Factory\MailerFactory;
use League\Plates\Engine;
use PHPMailer\PHPMailer\Exception;

class TwoFactorMail
{
    private Engine $templateEngine;

    /**
     * TwoFactorMail constructor.
     * @param Engine $templateEngine
     */
    public function __construct(Engine $templateEngine)
    {
        $this->templateEngine = $templateEngine;
    }

    /**
     * Sends the two factor email
     *
     * @param string $artistEmail
     * @param string $artistName
     * @param string $twoFactorCode
     * @throws Exception
     */
    public function sendMail(string $artistEmail, string $artistName, string $twoFactorCode): void
    {
        $renderedHtmlMail = $this->templateEngine->render(
            'mailing::TwoFactorCodeHtml',
            [
                'artistName' => $artistName,
                'twoFactorCode' => $twoFactorCode,
            ],
        );
        $renderedTextMail = $this->templateEngine->render(
            'mailing::TwoFactorCodeText',
            [
                'artistName' => $artistName,
                'twoFactorCode' => $twoFactorCode,
            ],
        );

        $mailer = MailerFactory::getMailer();
        $mailer->setFrom(getenv('MAILER_FROM'));
        $mailer->addAddress($artistEmail);
        $mailer->AltBody = $renderedTextMail;
        $mailer->Body = $renderedHtmlMail;
        $mailer->isHTML();

        $mailer->send();
    }
}