<?php

namespace App\Support\SignUp;

use Symfony\Component\HttpKernel\Exception\HttpException;

/** Too many sign-ups from one network or for one address in an hour (429). */
class TooManySignUps extends HttpException
{
    public function __construct()
    {
        parent::__construct(429, __('signup.too_many'));
    }
}
