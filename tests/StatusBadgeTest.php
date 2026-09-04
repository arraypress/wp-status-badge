<?php
/**
 * Status badge tests.
 *
 * @package ArrayPress\StatusBadge
 */

declare( strict_types=1 );

namespace ArrayPress\StatusBadge\Tests;

use ArrayPress\StatusBadge\StatusBadge;
use PHPUnit\Framework\TestCase;

/**
 * A badge is a word, an icon and a colour, and the whole library is a lookup
 * table deciding which colour.
 *
 * That makes it look too simple to test, which is exactly why it is worth
 * testing: the failure mode is not a crash, it is a refund showing green.
 * Nothing about the page looks wrong, the status is spelled correctly, and
 * the colour says the opposite of the word. Every one of these tests exists
 * because a mapping is a fact somebody has to have decided on purpose.
 *
 * The other half is escaping. A status is a database value — an order state,
 * a licence state, whatever a plugin happened to store — and it goes into a
 * class attribute and into text.
 */
final class StatusBadgeTest extends TestCase {

	/**
	 * Forget what was enqueued.
	 */
	protected function setUp(): void {
		$GLOBALS['sb_enqueued']   = [];
		$GLOBALS['sb_registered'] = [];

		// The "already registered" flag is static, so without this the first
		// test to construct a badge is the only one that can observe the
		// registration.
		$flag = new \ReflectionProperty( StatusBadge::class, 'registered' );
		$flag->setValue( null, false );
	}

	/**
	 * A status maps to the type its meaning demands.
	 *
	 * One per group rather than all sixty: what is being pinned is that each
	 * group exists and resolves, not the whole table twice.
	 *
	 * @dataProvider statusProvider
	 *
	 * @param string $status   A status value.
	 * @param string $expected The type it should be.
	 */
	#[\PHPUnit\Framework\Attributes\DataProvider( 'statusProvider' )]
	public function test_a_status_maps_to_its_type( string $status, string $expected ): void {
		$this->assertSame( $expected, ( new StatusBadge() )->get_type( $status ) );
	}

	/**
	 * One status per group, and the ones most likely to be got wrong.
	 *
	 * @return array<string, array{0: string,1: string}>
	 */
	public static function statusProvider(): array {
		return [
			'active is good'             => [ 'active', StatusBadge::SUCCESS ],
			'paid is good'               => [ 'paid', StatusBadge::SUCCESS ],
			'pending is not yet'         => [ 'pending', StatusBadge::WARNING ],
			'processing is not yet'      => [ 'processing', StatusBadge::WARNING ],
			'failed is bad'              => [ 'failed', StatusBadge::DANGER ],

			// The one worth spelling out. A refund is a normal, successful
			// business event and it is still not a green badge: somebody
			// scanning a list of orders for problems needs it to stand out.
			'refunded is bad'            => [ 'refunded', StatusBadge::DANGER ],

			'new is neutral news'        => [ 'new', StatusBadge::INFO ],
			'archived is just off'       => [ 'archived', StatusBadge::DEFAULT ],
			'inactive is just off'       => [ 'inactive', StatusBadge::DEFAULT ],

			// Both spellings, because the two live side by side in real data
			// and one of them silently falling to grey is the bug.
			'cancelled, two ells'        => [ 'cancelled', StatusBadge::DANGER ],
			'canceled, one ell'          => [ 'canceled', StatusBadge::DANGER ],

			// And both separators, for the same reason.
			'on-hold with a hyphen'      => [ 'on-hold', StatusBadge::WARNING ],
			'on_hold with an underscore' => [ 'on_hold', StatusBadge::WARNING ],
		];
	}

	/**
	 * An unknown status is grey rather than a guess.
	 *
	 * Grey says "this library has nothing to tell you about this word", which
	 * is true. Any other colour would be a claim.
	 */
	public function test_an_unknown_status_is_grey(): void {
		$this->assertSame( StatusBadge::DEFAULT, ( new StatusBadge() )->get_type( 'flurgle' ) );
	}

	/**
	 * Case and surrounding space do not change the answer.
	 *
	 * Statuses arrive from databases, from CSV imports and from APIs, and
	 * " Active" is the same fact as "active".
	 */
	public function test_a_status_is_matched_loosely(): void {
		$badge = new StatusBadge();

		$this->assertSame( StatusBadge::SUCCESS, $badge->get_type( 'ACTIVE' ) );
		$this->assertSame( StatusBadge::SUCCESS, $badge->get_type( '  Active  ' ) );
	}

	/**
	 * A caller's own mapping wins over the defaults.
	 */
	public function test_a_custom_mapping_overrides_a_default(): void {
		$badge = new StatusBadge( [ 'refunded' => StatusBadge::INFO, 'churned' => StatusBadge::DANGER ] );

		$this->assertSame( StatusBadge::INFO, $badge->get_type( 'refunded' ) );
		$this->assertSame( StatusBadge::DANGER, $badge->get_type( 'churned' ) );

		// And the rest of the table is still there.
		$this->assertSame( StatusBadge::SUCCESS, $badge->get_type( 'active' ) );
	}

	/**
	 * The type can be forced for one badge without changing the map.
	 */
	public function test_a_type_can_be_overridden_for_one_badge(): void {
		$badge = new StatusBadge();
		$html  = $badge->render( 'active', StatusBadge::DANGER );

		$this->assertStringContainsString( 'wp-status-badge--danger', $html );
		$this->assertSame( StatusBadge::SUCCESS, $badge->get_type( 'active' ), 'The override leaked into the map.' );
	}

	/**
	 * A label is made readable, and can be replaced.
	 */
	public function test_a_label_is_readable(): void {
		$this->assertSame( 'In Progress', StatusBadge::format_label( 'in_progress' ) );
		$this->assertSame( 'On Hold', StatusBadge::format_label( 'on-hold' ) );

		$this->assertStringContainsString( '>Awaiting payment</span>', ( new StatusBadge() )->render( 'pending', null, 'Awaiting payment' ) );
	}

	/**
	 * The icon differs by type, and is hidden from assistive technology.
	 *
	 * A dashicon is a font glyph in a :before, so a screen reader either says
	 * nothing or says something meaningless — and the word beside it already
	 * carries the whole meaning. Without aria-hidden the badge announces
	 * itself twice, once as noise.
	 */
	public function test_the_icon_is_decorative(): void {
		$html = ( new StatusBadge() )->render( 'active' );

		$this->assertStringContainsString( 'dashicons-yes-alt', $html );
		$this->assertStringContainsString( 'aria-hidden="true"', $html );

		// A different type gets a different glyph, or the icon is decoration
		// that decorates nothing.
		$this->assertStringNotContainsString( 'dashicons-yes-alt', ( new StatusBadge() )->render( 'failed' ) );
	}

	/**
	 * The meaning survives without colour.
	 *
	 * Colour alone is not information: about one man in twelve cannot
	 * separate the red badge from the green one. The word is always there and
	 * the icon differs per type, so the badge reads in greyscale, in print,
	 * and in forced-colours mode.
	 */
	public function test_the_meaning_does_not_depend_on_colour(): void {
		$badge = new StatusBadge();
		$icons = [];

		foreach ( StatusBadge::get_types() as $type ) {
			$icons[] = $badge->get_icon( $type );
		}

		$this->assertSame( $icons, array_unique( $icons ), 'Two types share an icon, so only colour tells them apart.' );
	}

	/**
	 * A status out of a database is escaped.
	 *
	 * It reaches a class attribute by way of the type and the text by way of
	 * the label, and neither is a value this library chose.
	 */
	public function test_a_status_is_escaped(): void {
		$html = ( new StatusBadge() )->render( '"><script>alert(1)</script>' );

		$this->assertStringNotContainsString( '<script', $html );

		// The label, specifically: the markup legitimately contains `">` at
		// the end of every attribute, so the whole string is the wrong thing
		// to look at.
		$this->assertStringContainsString( '&lt;script&gt;', $html );
	}

	/**
	 * A type forced by a caller is escaped too.
	 *
	 * It goes straight into the class attribute without passing through the
	 * map, so it is the one value here that is never looked up.
	 */
	public function test_a_forced_type_that_is_not_a_type_is_refused(): void {
		$html = ( new StatusBadge() )->render( 'active', '" onmouseover="alert(1)' );

		// Escaped, so it could not break out of the attribute either way —
		// but a class nothing styles is a badge with no colour and a fallback
		// glyph, which reads as a status the library does not know rather
		// than as a typo in the call. So an unrecognised type is refused.
		$this->assertStringNotContainsString( 'onmouseover', $html );

		// And what it falls back to is the status's own type, not grey.
		// Grey would throw away something known — that 'active' is good —
		// to punish a mistake in a different argument.
		$this->assertStringContainsString( 'wp-status-badge--success', $html );
	}

	/**
	 * Every type in the map is one the library can actually draw.
	 *
	 * A mapping to a type with no icon and no CSS renders a badge that is
	 * grey and iconless, which looks like a status the library does not know
	 * rather than a typo in the table.
	 */
	public function test_every_mapped_type_is_a_real_type(): void {
		$types   = StatusBadge::get_types();
		$unknown = array_diff( array_unique( array_values( ( new StatusBadge() )->get_map() ) ), $types );

		$this->assertSame( [], array_values( $unknown ) );
	}

	/**
	 * Rendering asks for the stylesheet.
	 */
	public function test_rendering_enqueues_the_stylesheet(): void {
		( new StatusBadge() )->render( 'active' );

		$this->assertContains( StatusBadge::handle(), $GLOBALS['sb_enqueued'] );
	}

	/**
	 * The stylesheet is registered once, against dashicons.
	 *
	 * Dashicons as a dependency rather than an assumption: the badge draws an
	 * icon from that font, and on the front end nothing has loaded it.
	 */
	public function test_the_stylesheet_is_registered_with_its_dependency(): void {
		new StatusBadge();
		new StatusBadge();

		$registered = $GLOBALS['sb_registered'][ StatusBadge::handle() ] ?? null;

		$this->assertNotNull( $registered, 'The stylesheet was never registered.' );
		$this->assertContains( 'dashicons', $registered['deps'] );
	}
	/**
	 * The handle is derived, not written down.
	 *
	 * Asserted from the source rather than by calling it, because in this
	 * suite the library is unprefixed and the derived answer and the literal
	 * one are the same string — the difference only appears in a build, which
	 * is where it would go wrong and where nobody is looking.
	 *
	 * What it protects: Strauss renames namespaces and does not rename
	 * strings. Two plugins each bundling a prefixed copy would both register
	 * a style called 'wp-status-badge', the second registration is a silent
	 * no-op, and whichever plugin loaded first decides which version of the
	 * CSS the other one gets.
	 */
	public function test_the_stylesheet_handle_is_derived_from_the_namespace(): void {
		$source = (string) file_get_contents( dirname( __DIR__ ) . '/src/StatusBadge.php' );

		preg_match_all( '/(?:wp_enqueue_style|arraypress_register_composer_style)\(\s*([^,\n]+)/', $source, $calls );

		$this->assertNotEmpty( $calls[1], 'Nothing registers or enqueues the stylesheet any more.' );

		foreach ( $calls[1] as $argument ) {
			$this->assertStringContainsString(
				'self::handle()',
				trim( $argument ),
				'The handle is a literal, so two prefixed copies would share it.'
			);
		}
	}

}
