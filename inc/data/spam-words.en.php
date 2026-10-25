<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Jinyu built-in spam-comment keyword list (English edition).
 *
 * Returned as an array of terms, consumed by the JINYU_DEFAULT_SPAM_WORDS
 * constant in functions.php (imploded into a comma-separated string) and used as
 * the default value of the admin "Spam comment keywords" field and as the
 * runtime fallback when the field is empty.
 *
 * Matching is case-insensitive substring matching (stripos), so entries should be
 * distinctive phrases rather than single short words, to avoid blocking
 * legitimate comments.
 *
 * Extend it from a child theme or mu-plugin via
 * add_filter( 'jinyu_default_spam_words', ... ).
 *
 * @package         WordPress
 * @subpackage      Jinyu
 */

return [
	// Gambling / betting.
	'online casino',
	'free spins',
	'jackpot',
	'slot machine',
	'roulette',
	'blackjack',
	'sports betting',
	'betting odds',
	'free bet',
	'poker',
	'texas hold em',
	'lottery',
	'lotto',
	'bingo',
	'prize draw',
	'wager',
	'paylines',
	'jackpot winner',
	'lucky draw',
	'baccarat',

	// Pornography / adult content.
	'porno',
	'sex cam',
	'hentai',
	'camgirl',
	'escort',
	'escorts',
	'nudes',
	'naked girls',
	'real escorts',
	'sex deals',
	'hookup',
	'hook up',
	'sexy girls',
	'adult video',
	'sex toy',
	'milf',
	'bdsm',
	'fetish',

	// Scams / fraud.
	'you have won',
	'lottery winner',
	'claim your prize',
	'credit card',
	'gift card',
	'wire transfer',
	'western union',
	'moneygram',
	'crypto giveaway',
	'double your',
	'investment opportunity',
	'guaranteed returns',
	'no risk',
	'risk free',

	// Loans / finance.
	'cash advance',
	'personal loan',
	'bad credit',
	'no credit check',
	'loan offer',
	'refinance',
	'debt relief',
	'settle debt',
	'free consultation',
	'binary options',
	'crypto pump',

	// Invoices / certificates.
	'fake certificate',
	'fake diploma',
	'fake degree',
	'phd certificate',
	'transcript',
	'invoice',
	'receipt template',
	'blank receipt',
	'fake receipt',
	'pay slip',

	// Ghostwriting / grey-market services.
	'ghostwriter',
	'essay writer',
	'write my essay',
	'coursework help',
	'assignment help',
	'homework help',
	'thesis writing',
	'proofreading service',
	'paraphrasing tool',
	'turnitin',
	'ai essay',
	'essay mill',

	// Advertising / marketing.
	'seo service',
	'seo services',
	'backlinks',
	'link building',
	'guest post',
	'web design',
	'website design',
	'website traffic',
	'buy visitors',
	'buy traffic',
	'social media marketing',
	'email list',
	'newsletter',
	'affiliate marketing',
	'advertise your',
	'promote your',
	'brand promotion',
	'viral marketing',

	// Part-time jobs / earn-money sites.
	'part time job',
	'remote jobs',
	'freelance work',
	'data entry job',
	'typing job',
	'copy paste',
	'photo sharing',
	'refer friends',
	'referral link',
	'survey paid',
	'paid survey',
	'cashback',
	'watch ads',
	'play games earn',

	// Grey-market medicine / drugs.
	'discount pills',
	'buy pills',
	'no prescription',
	'diet pills',
	'buy steroids',
	'hormone',
	'testosterone',

	// Counterfeit goods / private servers / cheats.
	'replica watch',
	'fake bag',
	'fake shoes',
	'knockoff',
	'bootleg',
	'counterfeit',
	'hwid spoofer',
	'private server',
	'aimbot',
	'wallhack',

	// Traffic funnels / group invitations.
	'add me',
	'join my',
	'join our',
	'add my',
	'followers for sale',
	'traffic funnel',
	'click the link in my',
	'visit my profile',
	'pm me',
	'telegram group',
	'whatsapp group',
	'discord server',
	'telegram channel',

	// Generic spam phrases.
	'this is a test',
	'test test',
	'hello dear',
	'dear sir or madam',
	'kindly',
	'i am looking for',
	'i am interested in',
	'do you want to',
	'want to earn',
	'serious business',
	'business proposal',
	'urgent reply',
	'good day to you',
	'i hope this message finds you well',
	'dear customer',

];
