<?Php
/*
Plugin Name: Google Analytics via Measurement Protocol
Description: Send events, pageviews and transactions to Google Analytics using PHP.
Version: 1.0.0
Author: Baptiste Legrand
Author URI: https://twitter.com/Baptiste_L

This is largely base on Stu Miller's work.
See : http://www.stumiller.me/implementing-google-analytics-measurement-protocol-in-php-and-wordpress/

I claim no ownership on this code and only share in case it may be useful to someone else. Feel free to send pull requests if you think you can improve the code.
*/

define("GA_PROPERTY_ID", 'UA-XXXXXXXX-X'); // CHANGE THIS VALUE, USE YOUR GA PROPERTY ID


// Retrieve user's CID, if GA already created it. Otherwise : creates it.
function get_GA_CID()
{
  if (isset($_COOKIE['_ga']))
  {
    $pieces = explode(".", $_COOKIE["_ga"]);
	$version = $pieces[0];
	$domainDepth = $pieces[1];
	$cid1 = $pieces[2];
	$cid2 = $pieces[3];
$contents = array('version' => $version, 'domainDepth' => $domainDepth, 'cid' => $cid1.'.'.$cid2);
    $cid = $contents['cid'];
  }
  else
  {
	  $cid = gaGenUUID();
  }
  return $cid;
}


// Generate UUID v4 function - needed to generate a CID when one isn't available
function gaGenUUID()
{
  return sprintf( '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
    // 32 bits for "time_low"
    mt_rand( 0, 0xffff ), mt_rand( 0, 0xffff ),

    // 16 bits for "time_mid"
    mt_rand( 0, 0xffff ),

    // 16 bits for "time_hi_and_version",
    // four most significant bits holds version number 4
    mt_rand( 0, 0x0fff ) | 0x4000,

    // 16 bits, 8 bits for "clk_seq_hi_res",
    // 8 bits for "clk_seq_low",
    // two most significant bits holds zero and one for variant DCE1.1
    mt_rand( 0, 0x3fff ) | 0x8000,

    // 48 bits for "node"
    mt_rand( 0, 0xffff ), mt_rand( 0, 0xffff ), mt_rand( 0, 0xffff )
  );
}


// Send a hit to Google Analytics (a hit can be an event, a pageview or a transaction)
function GA_send_hit($data = null)
{
	$tid = GA_PROPERTY_ID;
	if($data)
	{
		// Standard params, always needed
		$args = array(
			'v' => 1,
			'tid' => $tid,
			'cid' => get_GA_CID(),
		);


		// Prepare a PAGEVIEW
		if ($data['t'] === 'pageview')
		{
			$args['t'] = $data['t'];
			$args['dp'] = $data['dp'];
			$args['dt'] = $data['dt'];
		}


		// Prepare an EVENT
		elseif($data['t'] === "event")
		{
			$args['t'] = $data['t'];
			$args['ec'] = $data['ec'];
			$args['ea'] = $data['ea'];
			$args['el'] = $data['el'];
			if(!is_null($data['ev'])) { $args['ev'] = $data['ev']; } else { $args['ev'] = 0; }
		}


		// Prepare a TRANSACTION Hit (should be followed by a Item hit)
		elseif($data['t'] === "transaction")
		{
			$args['t'] = $data['t'];
			$args['ti'] = $data['ti'];
			$args['tr'] = $data['tr'];
			$args['tt'] = $data['tt'];
		}

		// Prepare an ITEM Hit
		elseif($data['t'] === "item")
		{
			$args['t'] = $data['t'];
			$args['ti'] = $data['ti'];
			$args['in'] = $data['in'];
			$args['ip'] = $data['ip'];
			$args['iq'] = $data['iq'];
		}


		// SEND HIT TO GA
		if ( $args )
		{
			$url = 'http://www.google-analytics.com/collect?';
			$args = http_build_query($args);

			// You might want to log the payload sent to Google Analytics, in order to test it with Google Measurement Protocol Hit Validator

			$args = utf8_encode($args); // The payload must be UTF-8 encoded.
			$result = wp_remote_get($url.$args ); // careful, wp_remote_get is a wordpress function, use another one if you're not under Wordpress
			return $result;
		}
	}
}


// Send TRANSACTION hit (should be followed by a ITEM hit with the same transaction_ID)
function GA_send_transaction($transaction_ID, $transaction_ttc, $transaction_tax)
{
	$args = array
	(
		't' => 'transaction',
		'ti' => $transaction_ID,	// Transaction ID. Required.
		'tr' => $transaction_ttc,	// Montant TTC.
		'tt' => $transaction_tax,	// Tax
	);
	return GA_send_hit($args);
}

// Send ITEM hit (should follow a TRANSACTION hit) & should have the same transaction_ID
function GA_send_item($transaction_ID, $item_name, $item_price, $item_quantity = 1)
{
	$args = array(
		't' => 'item',
		'ti' => $transaction_ID,	// Same Transaction ID as for transaction hit
		'in' => $item_name,			// Item name (string)
		'ip' => $item_price,		// Item price
		'iq' => $item_quantity,		// Item Quantity
	);
	return GA_send_hit($args);
}


// Send EVENT hit
function GA_send_event($category, $action, $label, $value = NULL)
{
	$args = array
	(
		't' => 'event',
		'ec' => $category,
		'ea' => $action,
		'el' => $label,
		'ev' => $value,
	);
	GA_send_hit($args);
}


// Send VIRTUAL PAGEVIEW hit
function GA_send_pageview($title, $slug)
{
	$args = array
	(
		't' => 'pageview',
		'dt' => $title,
		'dp' => $slug,
	);
	GA_send_hit($args);
}