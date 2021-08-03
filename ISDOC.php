<?php

use Nette\DateTime;
use Nette\Latte\Engine;
use Nette\Templating\FileTemplate;

class ISDOC
{
    /**
       1: Faktura - daňový doklad
       2: Opravný daňový doklad (dobropis)
       3: Opravný daňový doklad (vrubopis)
       4: Zálohová faktura (nedaňový zálohový list)
       5: Daňový doklad při přijetí platby (daňový zálohový list)
       6: Opravný daňový doklad při přijetí platby (dobropis DZL)
       7: Zjednodušený daňový doklad
     */
    const TYPE_FAKTURA = 1;
    const TYPE_DOBROPIS = 2;
    const TYPE_VRUBOPIS = 3;
    const TYPE_ZALOHOVA_FAKTURA = 4;
    const TYPE_DANOVY_ZALOHOVY_LIST = 5;
    const TYPE_DOBROPIS_DZL = 6;
    const TYPE_ZJEDNODUSENY_DANOVY_DOKLAD = 7;

    private $doc;

    public function __construct($typDokladu, $cisloDokladu)
    {
        $this->doc = new stdClass;
        $this->doc->DocumentType = $typDokladu;
        $this->doc->ID = $cisloDokladu;
        $this->doc->IssueDate = NULL;
        $this->doc->Note = "";
        $this->doc->VATApplicable = 'false';
        $this->doc->TaxPointDate = NULL;
        $this->doc->LocalCurrencyCode = "CZK";
        $this->doc->lines = array();
        $this->doc->taxSubTotals = array();
        $this->doc->totalTax = 0;
        $this->doc->totalPrice = 0;
        $this->doc->totalPriceTaxed = 0;
        $this->doc->payments = array();
    }

    function guidv4($data = null)
    {
        // Generate 16 bytes (128 bits) of random data or use the data passed into the function.
        $data = substr($data, 0, 16);
        //$data = random_bytes(16);
        assert(strlen($data) == 16);

        // Set version to 0100
        $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
        // Set bits 6-7 to 10
        $data[8] = chr(ord($data[8]) & 0x3f | 0x80);

        // Output the 36 character UUID.
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }

    public function datumVystaveni($date)
    {
        $this->doc->IssueDate = $date->format('Y-m-d');
    }


    public function datumPlneniDph($date)
    {
        $this->doc->TaxPointDate = $date->format('Y-m-d');
    }

    public function addPayment($payment)
    {
        $this->doc->payments[] = $payment;
    }

    public function Dph($onOff = false)
    {
        if ($onOff) {
            $this->doc->VATApplicable = 'true';
        } else {
            $this->doc->VATApplicable = 'false';
        }
    }

    public function Note($message)
    {
        $this->doc->Note = $message;
    }

    public function MenaLokalni($mena)
    {
        $this->LocalCurrencyCode = $mena;
    }

    public function Dodavatel($dod)
    {
        $this->doc->supplier = $dod;
    }

    public function Odberated($odb)
    {
        $this->doc->customer = $odb;
    }



    public function addLine($line)
    {
        $this->doc->lines[] = $line;

        if (isset($this->doc->taxSubTotals[$line->taxPercent])) {
            //increment
            $this->doc->taxSubTotals[$line->taxPercent]->TaxableAmount += $line->totalPrice;
            $this->doc->taxSubTotals[$line->taxPercent]->TaxAmount += $line->totalTax;
            $this->doc->taxSubTotals[$line->taxPercent]->TaxInclusiveAmount += $line->totalPriceTaxed;
        } else {
            //create new
            $subtotal = new stdClass;
            $subtotal->TaxableAmount = $line->totalPrice;
            $subtotal->TaxAmount = $line->totalTax;
            $subtotal->TaxInclusiveAmount = $line->totalPriceTaxed;
            $subtotal->Percent = $line->taxPercent;
            $this->doc->taxSubTotals[$subtotal->Percent] = $subtotal;
        }

        $this->doc->totalTax += $line->totalTax;
        $this->doc->totalPrice += $line->totalPrice;
        $this->doc->totalPriceTaxed += $line->totalPriceTaxed;
    }

    public function getDoc()
    {
        return $this->doc;
    }

    public function finalize()
    {
        $this->doc->UUID = $this->guidv4(md5(serialize($this->doc)));
    }


    public function renderToString()
    {
        $template = new FileTemplate(__DIR__ . "/ISDOC.latte");
        $this->doc->formatType = 'isdoc';
        $this->doc->formatVersion = '6.0.1';
        $latte = new Engine;
        $template->registerFilter($latte);
        $template->doc = $this->doc;
        //$template->setTranslator($this->getTranslator());
        //header("Content-type: text/xml");
        //echo $template;
        return $template->__toString();
    }
}
