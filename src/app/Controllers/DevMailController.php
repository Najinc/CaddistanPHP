<?php

namespace App\Controllers;

use App\Auth\SafeRedirect;
use App\Core\HttpException;
use App\Core\Response;
use App\Mail\Mailer;

final class DevMailController extends Controller
{
    public function clear(): Response
    {
        if (!Mailer::enabled()) {
            throw HttpException::notFound();
        }

        Mailer::mailbox()->clear();
        $back = parse_url((string) $this->request->header('Referer'), PHP_URL_PATH);

        return $this->redirect(SafeRedirect::target($back, '/'));
    }
}
