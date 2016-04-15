<?php
/**
 * Cron for updating inbound Exchange emails
 * 
 * @Author Kanika Navla (kanikanavla@gmail.com)
 * 
 * We need to create 2 separate cron jobs that will interface
with a provided Exchange e-mail server (likely 2007-2010).
One cron job will have the responsibility of downloading
mail per each e-mail account in our system, and save it in
a "INBOUND" table.
 */
set_time_limit(0);
require_once dirname(__FILE__).'/../controller/Exchange.php';
$exc = new ExchangeController();
$exc->addEmailsToDB();