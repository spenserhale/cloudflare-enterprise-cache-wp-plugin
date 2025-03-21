<?php

namespace CF\EntCache\Tests;

use CF\EntCache\PurgeInputValidator;

class PurgeInputValidatorTest extends \WP_UnitTestCase
{
    public function testHostValidation()
    {
        static::assertInstanceOf(
            \WP_Error::class,
            PurgeInputValidator::validate('host', 'https://www.johnsmith.comfacial-cosmetic-surgery/brow-lift/'),
            'Expected error for invalid host'
        );

        static::assertNull(
            PurgeInputValidator::validate('host', 'www.johnsmith.com'),
            'Expected no error for valid host'
        );
    }

    public function testFileValidation()
    {
        static::assertInstanceOf(
            \WP_Error::class,
            PurgeInputValidator::validate('file', 'https://www.johnsmith.comfacial-cosmetic-surgery/brow-lift/'),
            'Expected error for invalid file'
        );

        static::assertNull(
            PurgeInputValidator::validate('file', 'https://www.johnsmith.com/facial-cosmetic-surgery/brow-lift/'),
            'Expected no error for valid file'
        );
    }
}
