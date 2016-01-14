<?php
declare(strict_types=1);
namespace ParagonIE\Halite\Asymmetric;

use \ParagonIE\Halite\Alerts\InvalidKey;
use \ParagonIE\Halite\Util as CryptoUtil;

final class EncryptionPublicKey extends PublicKey
{
    /**
     * @param string $keyMaterial - The actual key data
     * @param bool $signing - Is this a signing key?
     */
    public function __construct(string $keyMaterial = '', ...$args)
    {
        if (CryptoUtil::safeStrlen($keyMaterial) !== \Sodium\CRYPTO_BOX_PUBLICKEYBYTES) {
            throw new InvalidKey(
                'Encryption public key must be CRYPTO_BOX_PUBLICKEYBYTES bytes long'
            );
        }
        parent::__construct($keyMaterial, false);
    }
}
