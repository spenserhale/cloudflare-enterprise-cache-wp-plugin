<?php

namespace CF\EntCache\Tests;

use CF\EntCache\PurgeInputValidator;

class PurgeInputValidatorTest extends \WP_UnitTestCase
{
    public function testHostValidation()
    {
        static::assertInstanceOf(
            \WP_Error::class,
            PurgeInputValidator::validate('host', 'https://www.drschalit.comfacial-cosmetic-surgery/brow-lift/'),
            'Expected error for invalid host'
        );

        static::assertNull(
            PurgeInputValidator::validate('host', 'www.drschalit.com'),
            'Expected no error for valid host'
        );
    }
}
