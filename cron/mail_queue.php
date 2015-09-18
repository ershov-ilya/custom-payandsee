<?php
/**
 * from https://github.com/bezumkin/Tickets/blob/master/core/components/tickets/cron/mail_queue.php
 *
 * рассылка писем из очереди
 */

define('MODX_API_MODE', true);
require_once dirname(dirname(dirname(dirname(dirname(__FILE__))))) . '/index.php';
$modx->getService('error', 'error.modError');
$modx->getRequest();
$modx->setLogLevel(modX::LOG_LEVEL_ERROR);
$modx->setLogTarget('FILE');
$modx->error->message = null;

if ($modx->loadClass('PaySeeQueue')) {
	$q = $modx->newQuery('PaySeeQueue');
	$q->sortby('timestamp', 'ASC');
	$queue = $modx->getCollection('PaySeeQueue', $q);
	foreach ($queue as $letter) {
		if ($letter->Send()) {
			$letter->remove();
		}
	}
}
