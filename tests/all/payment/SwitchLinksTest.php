<?php

/**
 * "switch_links" cases (legacy Paymenttest cases 87, 90-97): linking a
 * partially-paid invoice to a PENDING gateway bill, toggled by the
 * `switch_links` configuration flag (true vs false), across offline payment,
 * chargeAccount and onetimeinvoice entry points, and the step-2-after-pending
 * retry flow.
 *
 * These scenarios require a real PENDING gateway state (a charge that the
 * gateway leaves pending) plus the partial-payment linking that depends on the
 * switch_links config and a prior pending bill. The mockup gateway used here
 * resolves charges synchronously (000 / rejected), so a genuine "pending then
 * link" state cannot be reproduced through the payment API alone — these need a
 * dedicated pending-gateway fixture. They are documented and skipped pending
 * that harness rather than asserted against a state we cannot create.
 *
 * The switch_links toggle itself is set via config:
 *   $this->tester->overrideConfigValue('billrun.payments.switch_links', true|false);
 */
class SwitchLinksTest extends \Codeception\Test\Unit
{
    /** @var \AcceptanceTester */
    protected $tester;

    // case 87 - switch links: partially paid invoice + link to pending bill, offline (fc) payment
    public function testSwitchLinksOfflinePayment()
    {
        $this->markTestSkipped('needs a pending gateway bill to link a partial offline payment to (switch_links=true)');
    }

    // case 90 - switch links via chargeAccount (online payment)
    public function testSwitchLinksChargeAccount()
    {
        $this->markTestSkipped('needs a pending gateway bill + switch_links=true; mockup resolves charges synchronously');
    }

    // case 91 - switch links via onetimeinvoice, online payment amount
    public function testSwitchLinksOnetimeInvoice()
    {
        $this->markTestSkipped('needs a pending gateway bill + switch_links=true');
    }

    // case 92 - partial by approved payment + partial by pending bill (online)
    public function testSwitchLinksPartialApprovedAndPending()
    {
        $this->markTestSkipped('needs a pending gateway bill alongside an approved payment (switch_links=true)');
    }

    // case 93 - as 92 with the pending portion larger than needed
    public function testSwitchLinksPartialPendingOverflow()
    {
        $this->markTestSkipped('needs a pending gateway bill larger than the remaining amount (switch_links=true)');
    }

    // case 94 - as 90 but with switch_links=false
    public function testNoSwitchLinksChargeAccount()
    {
        $this->markTestSkipped('needs a pending gateway bill + switch_links=false');
    }

    // case 95 - as 91 but with switch_links=false
    public function testNoSwitchLinksOnetimeInvoice()
    {
        $this->markTestSkipped('needs a pending gateway bill + switch_links=false');
    }

    // case 96 - as 92 but with switch_links=false
    public function testNoSwitchLinksPartialApprovedAndPending()
    {
        $this->markTestSkipped('needs a pending gateway bill + switch_links=false');
    }

    // case 97 - as 93 but with switch_links=false
    public function testNoSwitchLinksPartialPendingOverflow()
    {
        $this->markTestSkipped('needs a pending gateway bill + switch_links=false');
    }
}
