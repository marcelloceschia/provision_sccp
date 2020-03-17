<?php
declare(strict_types=1);

namespace SCCP\Crypto;

class HashAlgo {
    private const SHA1 = 0;
    private const SHA256 = 1;
}

class Certificate {
    function __construct(string $file) {
        $this->file = $file;


        $cert = openssl_x509_read( file_get_contents($this->file) );
        $this->certData = openssl_x509_parse( $cert );
        //print_r( $this->certData );
        //echo openssl_x509_fingerprint($cert);
        openssl_x509_free( $cert );
    }
    
    public function hashAsBase64(int $hashAlg)  : string  {
        return "";
    }
}

class Crypto {

    public static function certificateHashAsBase64(string $certificateFile, int $hashAlg)  : string  {
        return new Certificate($certificateFile)
                .hashAsBase64($hashAlg);
    }
}

class Signer {
}
