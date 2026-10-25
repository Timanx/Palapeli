<?php

declare(strict_types=1);

namespace Tests\Web;

use Tests\Support\Browser;
use Tests\Support\Game;
use Tests\Support\Page;
use Tests\Support\PalainfoTestCase;


/**
 * Discussion below a cipher on the page "Šifry" (thread "sifry_<year>_<checkpoint>").
 */
final class CipherDiscussionTest extends PalainfoTestCase
{
	public function testStartDiscussion(): void
	{
		Game::createDefault($this->db, gameStart: '-6 hours', gameEnd: '-1 hour');
		$this->addPost(0, 'Příspěvek ke startu');
		$this->addPost(2, 'Příspěvek ke druhé šifře');
		$browser = new Browser($this->db);

		// the start has the default checkpoint 0, which is left out of the URL
		foreach (['/game/ciphers', '/game/ciphers?year=' . Game::Year] as $url) {
			$page = $browser->open($url);
			self::assertSame(['Příspěvek ke startu'], $page->getTexts('.discussionPostContent'), $url);
			self::assertSame('sifry_' . Game::Year . '_0', self::getThreadField($page), $url);
		}

		$page = $browser->open('/game/ciphers?checkpoint=2&year=' . Game::Year);
		self::assertSame(['Příspěvek ke druhé šifře'], $page->getTexts('.discussionPostContent'));
		self::assertSame('sifry_' . Game::Year . '_2', self::getThreadField($page));
	}


	private function addPost(int $checkpoint, string $message): void
	{
		$this->db->query('INSERT INTO discussion ?', [
			'name' => 'Luštitel',
			'message' => $message,
			'thread' => 'sifry_' . Game::Year . '_' . $checkpoint,
		]);
	}


	/**
	 * Thread a new post is saved to.
	 */
	private static function getThreadField(Page $page): ?string
	{
		return $page->getDocument()->querySelector('input[name=thread]')?->getAttribute('value');
	}
}
