<?php

use Phinx\Migration\AbstractMigration;

class PaymentsTranslateConfigs extends AbstractMigration
{
    public function up()
    {
        $this->execute("
            update configs set display_name = 'payments.config.donation_vat_rate.name' where name = 'donation_vat_rate';
            update configs set description = null where name = 'donation_vat_rate';
            
            update configs set display_name = 'payments.config.tatrapay_sharedsecret.name' where name = 'tatrapay_sharedsecret';
            update configs set description = null where name = 'tatrapay_sharedsecret';
            
            update configs set display_name = 'payments.config.cardpay_sharedsecret.name' where name = 'cardpay_sharedsecret';
            update configs set description = null where name = 'cardpay_sharedsecret';

            update configs set display_name = 'payments.config.comfortpay_mid.name' where name = 'comfortpay_mid';
            update configs set description = null where name = 'comfortpay_mid';

            update configs set display_name = 'payments.config.comfortpay_ws.name' where name = 'comfortpay_ws';
            update configs set description = null where name = 'comfortpay_ws';
            
            update configs set display_name = 'payments.config.comfortpay_terminalid.name' where name = 'comfortpay_terminalid';
            update configs set description = null where name = 'comfortpay_terminalid';
            
            update configs set display_name = 'payments.config.comfortpay_sharedsecret.name' where name = 'comfortpay_sharedsecret';
            update configs set description = null where name = 'comfortpay_sharedsecret';
            
            update configs set display_name = 'payments.config.comfortpay_local_cert_path.name' where name = 'comfortpay_local_cert_path';
            update configs set description = 'payments.config.comfortpay_local_cert_path.description' where name = 'comfortpay_local_cert_path';
            
            update configs set display_name = 'payments.config.comfortpay_local_passphrase_path.name' where name = 'comfortpay_local_passphrase_path';
            update configs set description = 'payments.config.comfortpay_local_passphrase_path.description' where name = 'comfortpay_local_passphrase_path';
            
            
            update configs set display_name = 'payments.config.comfortpay_tem.name' where name = 'comfortpay_tem';
            update configs set description = 'payments.config.comfortpay_tem.description' where name = 'comfortpay_tem';
            
            update configs set display_name = 'payments.config.comfortpay_rem.name' where name = 'comfortpay_rem';
            update configs set description = 'payments.config.comfortpay_rem.description' where name = 'comfortpay_rem';
            
            update configs set display_name = 'payments.config.paypal_mode.name' where name = 'paypal_mode';
            update configs set description = 'payments.config.paypal_mode.description' where name = 'paypal_mode';
            
            update configs set display_name = 'payments.config.paypal_username.name' where name = 'paypal_username';
            update configs set display_name = 'payments.config.paypal_password.name' where name = 'paypal_password';
            update configs set display_name = 'payments.config.paypal_signature.name' where name = 'paypal_signature';
            update configs set display_name = 'payments.config.paypal_merchant.name' where name = 'paypal_merchant';
            
            update configs set display_name = 'payments.config.csob_merchant_id.name' where name = 'csob_merchant_id';
            update configs set description = 'payments.config.csob_merchant_id.description' where name = 'csob_merchant_id';
            
            update configs set display_name = 'payments.config.csob_shop_name.name' where name = 'csob_shop_name';
            update configs set description = 'payments.config.csob_shop_name.description' where name = 'csob_shop_name';
            
            update configs set display_name = 'payments.config.csob_bank_public_key_file_path.name' where name = 'csob_bank_public_key_file_path';
            update configs set description = 'payments.config.csob_bank_public_key_file_path.description' where name = 'csob_bank_public_key_file_path';
            
            update configs set display_name = 'payments.config.csob_private_key_file_path.name' where name = 'csob_private_key_file_path';
            update configs set description = 'payments.config.csob_private_key_file_path.description' where name = 'csob_private_key_file_path';
            
            update configs set display_name = 'payments.config.csob_mode.name' where name = 'csob_mode';
            update configs set description = 'payments.config.csob_mode.description' where name = 'csob_mode';
            
            update configs set display_name = 'payments.config.recurrent_payment_gateway_fail_delay.name' where name = 'recurrent_payment_gateway_fail_delay';
            update configs set description = 'payments.config.recurrent_payment_gateway_fail_delay.description' where name = 'recurrent_payment_gateway_fail_delay';
            
            
            update configs set display_name = 'payments.config.recurrent_payment_charges.name' where name = 'recurrent_payment_charges';
            update configs set description = 'payments.config.recurrent_payment_charges.description' where name = 'recurrent_payment_charges';
            
            update configs set display_name = 'payments.config.gopay_go_id.name' where name = 'gopay_go_id';
            update configs set display_name = 'payments.config.gopay_client_id.name' where name = 'gopay_client_id';
            update configs set display_name = 'payments.config.gopay_client_secret.name' where name = 'gopay_client_secret';
            update configs set display_name = 'payments.config.gopay_mode.name' where name = 'gopay_mode';
            update configs set display_name = 'payments.config.gopay_recurrence_date_to.name' where name = 'gopay_recurrence_date_to';
            
            
            update configs set display_name = 'payments.config.gopay_eet_enabled.name' where name = 'gopay_eet_enabled';
            update configs set description = 'payments.config.gopay_eet_enabled.description' where name = 'gopay_eet_enabled';
            
            update configs set display_name = 'payments.config.tatrapay_mid.name' where name = 'tatrapay_mid';
            
            update configs set display_name = 'payments.config.cardpay_mid.name' where name = 'cardpay_mid';
        ");
    }

    public function down()
    {

    }
}
