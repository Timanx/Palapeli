<?php

declare(strict_types=1);

namespace Tests\Support;

use Dom\Element;
use Dom\HTMLDocument;


/**
 * A page returned by the simulated browser, with helpers to read the Palainfo screens.
 */
final class Page
{
	private ?HTMLDocument $document = null;


	public function __construct(
		public readonly string $url,
		public readonly int $httpCode,
		public readonly string $html,
	) {
	}


	public function getDocument(): HTMLDocument
	{
		return $this->document ??= HTMLDocument::createFromString($this->html, LIBXML_NOERROR);
	}


	/**
	 * Form of the AKCE screen by its component name (codeInput, exitTimeInput, exitTimeNow, askForDead, askForEnd).
	 */
	public function getForm(string $name): ?Element
	{
		return $this->getDocument()->querySelector('form#frm-actionScreen-' . $name);
	}


	public function hasForm(string $name): bool
	{
		return $this->getForm($name) !== null;
	}


	/**
	 * Messages in the green/blue/red boxes ("flash success|info|error"), including the static hints of the screens.
	 * @return list<array{type: string, text: string}>
	 */
	public function getFlashes(): array
	{
		$flashes = [];
		foreach ($this->getDocument()->querySelectorAll('.flash') as $element) {
			$classes = preg_split('~\s+~', trim($element->getAttribute('class') ?? '')) ?: [];
			$type = array_values(array_diff($classes, ['flash']))[0] ?? '';
			$flashes[] = ['type' => $type, 'text' => self::normalize($element->textContent ?? '')];
		}

		return $flashes;
	}


	public function hasFlash(string $type, string $text): bool
	{
		foreach ($this->getFlashes() as $flash) {
			if ($flash['type'] === $type && str_contains($flash['text'], $text)) {
				return true;
			}
		}

		return false;
	}


	/**
	 * Heading above the code input of the AKCE screen (e.g. "KÓD STANOVIŠTĚ 2", "ŘEŠENÍ ŠIFRY"); null when
	 * the team cannot enter anything.
	 */
	public function getCodeHeading(): ?string
	{
		$heading = $this->getDocument()->querySelector('#actions-container > .checkpoint-heading');
		return $heading === null ? null : self::normalize($heading->textContent ?? '');
	}


	/**
	 * Heading of the departure form, e.g. "ODCHOD ZE STANOVIŠTĚ 1".
	 */
	public function getExitHeading(): ?string
	{
		$heading = $this->getDocument()->querySelector('.exit-time-forms-container > .checkpoint-heading');
		return $heading === null ? null : self::normalize($heading->textContent ?? '');
	}


	/**
	 * Text of the total hint button, e.g. "TOTÁLKA K ŠIFŘE 1"; null when the hint is not offered.
	 */
	public function getHintButton(): ?string
	{
		$button = $this->getDocument()->querySelector('.open-dead-form');
		return $button === null ? null : self::normalize($button->textContent ?? '');
	}


	public function hasQuitButton(): bool
	{
		return $this->getDocument()->querySelector('.open-end-form') !== null;
	}


	/**
	 * State of the wait for the cipher: "waiting" (orange countdown), "ready" (green) or null (no wait screen).
	 */
	public function getCipherWaitState(): ?string
	{
		$wait = $this->getCipherWait();
		if ($wait === null) {
			return null;
		}

		return str_contains($wait->getAttribute('class') ?? '', 'waiting') ? 'waiting' : 'ready';
	}


	public function getCipherWait(): ?Element
	{
		return $this->getDocument()->querySelector('#cipher-wait');
	}


	/**
	 * Confirmation screen opened right after the page loads (after a wrong confirmation code).
	 */
	public function opensScreen(string $function): bool
	{
		// the call at the end of the inline script, not the function definition
		return (bool) preg_match('~^\s*' . preg_quote($function, '~') . '\(\);~m', $this->html);
	}


	public function getText(): string
	{
		return self::normalize($this->getDocument()->body->textContent ?? '');
	}


	/**
	 * Texts of the elements matching the selector.
	 * @return list<string>
	 */
	public function getTexts(string $selector): array
	{
		$texts = [];
		foreach ($this->getDocument()->querySelectorAll($selector) as $element) {
			$texts[] = self::normalize($element->textContent ?? '');
		}

		return $texts;
	}


	private static function normalize(string $text): string
	{
		return trim((string) preg_replace('~\s+~u', ' ', str_replace("\u{a0}", ' ', $text)));
	}
}
