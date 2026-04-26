<?php
// literumilo_scan_morphemes.php
// Checks synthesis of prefixes and morphemes with limited combinability.
// Translated from Python by Claude. Original author: Klivo Lendon.

require_once __DIR__ . '/literumilo_entry.php';
require_once __DIR__ . '/literumilo_morpheme_list.php';

// ---------------------------------------------------------------------------
// Prefix checkers
// ---------------------------------------------------------------------------

function check_bo(int $idx, MorphemeList $ml): bool {
	//if ($idx !== 0) return false;
	// modifica apportata per gestire casi come fi-ge-fianĉ-o-j
	if (!check_first($idx, $ml)) return false;
	
	if ($ml->get_last_index() - $idx > 0) {
		$next = $ml->get($idx + 1);
		if ($next && $next->meaning === Meaning::PARENCO) return true;
	}
	return false;
}

function check_cis(int $idx, MorphemeList $ml): bool {
	//if ($idx !== 0) return false;
	// modifica apportata per gestire casi come fi-ge-fianĉ-o-j
	if (!check_first($idx, $ml)) return false;

	if ($ml->get_last_index() - $idx > 0) {
		$next = $ml->get($idx + 1);
		if ($next) {
			$m = $next->meaning;
			if ($m === Meaning::RIVERO || $m === Meaning::MONTO || $m === Meaning::MONTARO) return true;
		}
	}
	return false;
}

function check_cxi(int $idx, MorphemeList $ml): bool {
	if ($idx === 0) {
		$t = $ml->type_of_ending();
		return $t === POS::Adjective || $t === POS::Adverb;
	}
	return false;
}

function check_eks(int $idx, MorphemeList $ml): bool {
	//if ($idx !== 0) return false;
	// modifica apportata per gestire casi come fi-ge-fianĉ-o-j
	if (!check_first($idx, $ml)) return false;

	$last = $ml->get_last_index();
	for ($n = $idx + 1; $n <= $last; $n++) {
		$e = $ml->get($n);
		if ($e && is_person($e->meaning)) return true;
	}
	return false;
}

function check_ge(int $idx, MorphemeList $ml): bool {
	//if ($idx !== 0) return false;
	// modifica apportata per gestire casi come fi-ge-fianĉ-o-j
	if (!check_first($idx, $ml)) return false;

	$last = $ml->get_last_index();
	for ($n = $idx + 1; $n <= $last; $n++) {
		$e = $ml->get($n);
		if ($e && (is_person($e->meaning) || is_animal($e->meaning))) return true;
	}
	return false;
}

function check_kun(int $idx, MorphemeList $ml): bool {
	//if ($idx !== 0) return false;
	// modifica apportata per gestire casi come fi-ge-fianĉ-o-j
	if (!check_first($idx, $ml)) return false;

	if (check_prepositional_prefix($idx, $ml)) return true;
	$last = $ml->get_last_index();
	for ($n = $idx + 1; $n <= $last; $n++) {
		$e = $ml->get($n);
		if ($e && $e->part_of_speech === POS::Substantive) return true;
	}
	return false;
}

function check_mal(int $idx, MorphemeList $ml): bool {
	//if ($idx !== 0) return false;
	// modifica apportata per gestire casi come fi-ge-fianĉ-o-j
	if (!check_first($idx, $ml)) return false;

	$last = $ml->get_last_index();
	$toe  = $ml->type_of_ending();
	if ($last > 0 && ($toe === POS::Verb || $toe === POS::Adjective || $toe === POS::Adverb)) return true;
	for ($n = $idx + 1; $n <= $last; $n++) {
		$e = $ml->get($n);
		if ($e) {
			$pos = $e->part_of_speech;
			if ($pos === POS::Verb || $pos === POS::SubstantiveVerb || $pos === POS::Adjective || $pos === POS::Adverb) return true;
		}
	}
	return false;
}

function check_ne(int $idx, MorphemeList $ml): bool {
	//if ($idx !== 0) return false;
	// modifica apportata per gestire casi come fi-ge-fianĉ-o-j
	if (!check_first($idx, $ml)) return false;

	$last = $ml->get_last_index();
	$toe  = $ml->type_of_ending();
	if ($last > 0 && ($toe === POS::Adjective || $toe === POS::Adverb)) return true;
	for ($n = $idx + 1; $n <= $last; $n++) {
		$e = $ml->get($n);
		if ($e) {
			$pos = $e->part_of_speech;
			if ($pos === POS::Adjective || $pos === POS::Participle) return true;
			if ($e->morpheme === 'ad' || $e->morpheme === 'ec')		return true;
		}
	}
	return false;
}

function check_po(int $idx, MorphemeList $ml): bool {
	//if ($idx !== 0) return false;
	// modifica apportata per gestire casi come fi-ge-fianĉ-o-j
	if (!check_first($idx, $ml)) return false;

	return $ml->get_last_index() > 0 && $ml->type_of_ending() === POS::Adverb;
}

function check_pra(int $idx, MorphemeList $ml): bool {
	//if ($idx !== 0) return false;
	// modifica apportata per gestire casi come fi-ge-fianĉ-o-j
	if (!check_first($idx, $ml)) return false;

	$last = $ml->get_last_index();
	$diff = $last - $idx;
	if ($diff > 0) {
		$next = $ml->get($idx + 1);
		if ($next) {
			$pos = $next->part_of_speech;
			if ($pos === POS::Substantive || $pos === POS::SubstantiveVerb) return true;
		}
	}
	if ($diff > 1) {
		$next = $ml->get($idx + 2);
		if ($next && $next->part_of_speech === POS::Participle) return true;
	}
	return false;
}

function check_pseuxdo(int $idx, MorphemeList $ml): bool {
	//if ($idx !== 0) return false;
	// modifica apportata per gestire casi come fi-ge-fianĉ-o-j
	if (!check_first($idx, $ml)) return false;

	$diff = $ml->get_last_index() - $idx;
	if ($diff > 0) {
		$next = $ml->get($idx + 1);
		if ($next) {
			$pos = $next->part_of_speech;
			return $pos === POS::Substantive || $pos === POS::SubstantiveVerb || $pos === POS::Adjective;
		}
	}
	return false;
}

function check_sen(int $idx, MorphemeList $ml): bool {
	//if ($idx !== 0) return false;
	// modifica apportata per gestire casi come fi-ge-fianĉ-o-j
	if (!check_first($idx, $ml)) return false;

	if (check_prepositional_prefix($idx, $ml)) return true;
	$last = $ml->get_last_index();
	$le   = $ml->get($last);
	if ($le) {
		$m = $le->morpheme;
		if ($m === 'ul' || $m === 'aĵ' || $m === 'ej') return true;
	}
	return false;
}

function check_sin(int $idx, MorphemeList $ml): bool {
	//if ($idx !== 0) return false;
	// modifica apportata per gestire casi come fi-ge-fianĉ-o-j
	if (!check_first($idx, $ml)) return false;

	$last = $ml->get_last_index();
	for ($n = $idx + 1; $n <= $last; $n++) {
		$e = $ml->get($n);
		if ($e && $e->transitivity === Transitivity::Transitive) return true;
	}
	return false;
}

function check_sub_super_sur(int $idx, MorphemeList $ml): bool {
	if (check_prepositional_prefix($idx, $ml)) return true;
	$toe = $ml->type_of_ending();
	if ($ml->get_last_index() > 0 &&
		($toe === POS::Substantive || $toe === POS::SubstantiveVerb)) return true;
	return false;
}

function check_first(int $idx, MorphemeList $ml): bool {
	// return $idx === 0;
	// modifica aggiunta per gestire casi come fi-ge-fianĉ-o-j
    if ($idx === 0) return true;

    $prev = $ml->get($idx - 1);
    return $prev !== null && $prev->morpheme === 'fi';
}

function check_adverbial_prefix(int $idx, MorphemeList $ml): bool {
	$last = $ml->get_last_index();
	$toe  = $ml->type_of_ending();
	if ($last > 0 && $toe === POS::Verb) return true;
	for ($n = $idx + 1; $n <= $last; $n++) {
		$e = $ml->get($n);
		if ($e) {
			$pos = $e->part_of_speech;
			if ($pos === POS::Verb || $pos === POS::SubstantiveVerb) return true;
		}
	}
	return false;
}

function check_prepositional_prefix(int $idx, MorphemeList $ml): bool {
	$last = $ml->get_last_index();
	$toe  = $ml->type_of_ending();
	if ($last > 0 && ($toe === POS::Adjective || $toe === POS::Adverb)) return true;
	for ($n = $idx + 1; $n <= $last; $n++) {
		$e = $ml->get($n);
		if ($e) {
			$pos = $e->part_of_speech;
			if ($pos === POS::Verb || $pos === POS::SubstantiveVerb) return true;
		}
	}
	return false;
}

/**
 * Dispatcher: checks validity of a prefix in context.
 */
function check_prefix(string $prefix, int $idx, MorphemeList $ml): bool {
	$entry = $ml->get($idx);
	if (!$entry) return false;
	if ($entry->part_of_speech === POS::TechPrefix) return $idx === 0;

	switch ($prefix) {
		case 'al':		return check_prepositional_prefix($idx, $ml);
		case 'anstataŭ': return check_first($idx, $ml);
		case 'antaŭ':	return check_first($idx, $ml);
		case 'apud':	return check_prepositional_prefix($idx, $ml);
		case 'bo':		return check_bo($idx, $ml);
		case 'cis':		return check_cis($idx, $ml);
		case 'ĉe':		return check_prepositional_prefix($idx, $ml);
		case 'ĉi':		return check_cxi($idx, $ml);
		case 'ĉirkaŭ':	return check_first($idx, $ml);
		case 'de':		return check_prepositional_prefix($idx, $ml);
		case 'dis':		return check_adverbial_prefix($idx, $ml);
		case 'dum':		return check_prepositional_prefix($idx, $ml);
		case 'ek':		return check_adverbial_prefix($idx, $ml);
		case 'eks':		return check_eks($idx, $ml);
		case 'ekster':	return check_first($idx, $ml);
		case 'el':		return check_prepositional_prefix($idx, $ml);
		case 'en':		return check_prepositional_prefix($idx, $ml);
		case 'fi':		return check_first($idx, $ml);
		case 'for':		return check_adverbial_prefix($idx, $ml);
		case 'ge':		return check_ge($idx, $ml);
		case 'ĝis':		return check_prepositional_prefix($idx, $ml);
		case 'inter':	return check_first($idx, $ml);
		case 'kontraŭ':	return check_first($idx, $ml);
		case 'krom':	return check_first($idx, $ml);
		case 'kun':		return check_kun($idx, $ml);
		case 'laŭ':		return check_prepositional_prefix($idx, $ml);
		case 'mal':		return check_mal($idx, $ml);
		case 'mis':		return check_adverbial_prefix($idx, $ml);
		case 'ne':		return check_ne($idx, $ml);
		case 'per':		return check_prepositional_prefix($idx, $ml);
		case 'pli':		return check_adverbial_prefix($idx, $ml);
		case 'po':		return check_po($idx, $ml);
		case 'por':		return check_prepositional_prefix($idx, $ml);
		case 'post':	return check_prepositional_prefix($idx, $ml);
		case 'pra':		return check_pra($idx, $ml);
		case 'preter':	return check_prepositional_prefix($idx, $ml);
		case 'pri':		return check_prepositional_prefix($idx, $ml);
		case 'pro':		return check_prepositional_prefix($idx, $ml);
		case 'pseŭdo':	return check_pseuxdo($idx, $ml);
		case 're':		return check_adverbial_prefix($idx, $ml);
		case 'retro':	return check_first($idx, $ml);
		case 'sen':		return check_sen($idx, $ml);
		case 'sin':		return check_sin($idx, $ml);
		case 'sub':		return check_sub_super_sur($idx, $ml);
		case 'super':	return check_sub_super_sur($idx, $ml);
		case 'sur':		return check_sub_super_sur($idx, $ml);
		case 'tra':		return check_prepositional_prefix($idx, $ml);
		case 'trans':	return check_prepositional_prefix($idx, $ml);
	}
	return false;
}

// ---------------------------------------------------------------------------
// Limited synthesis / separator checks
// ---------------------------------------------------------------------------

function check_limited_synthesis(string $morpheme, int $idx, MorphemeList $ml): bool {
	$last  = $ml->get_last_index();
	$entry = $ml->get($idx);
	if (!$entry) return false;
	$pos	 = $entry->part_of_speech;
	$meaning = $entry->meaning;

	if ($pos === POS::Verb || $pos === POS::SubstantiveVerb) {
		if ($idx > 0) {
			$prev = $ml->get($idx - 1);
			if ($prev && $prev->synthesis !== Synthesis::Prefix) return false;
		}
		// if ($idx < $last) {
			// $next = $ml->get($idx + 1);
			// if ($next && $next->synthesis !== Synthesis::Suffix && $next->synthesis !== Synthesis::Participle) return false;
		// }
		if ($idx < $last) {
			$next = $ml->get($idx + 1);
			if ($next) {
				$next_syn = $next->synthesis;
				$next_pos = $next->part_of_speech;
				// A Limited SubstantiveVerb at position 0 may act as the first
				// element of a nominal compound (e.g. or.font.o, or.kolor.o).
				// In that case a free (NLM) substantive root following it is valid.
				$is_free_root_after_first = (
					$idx === 0 &&
					$next_syn === Synthesis::UnLimited &&
					($next_pos === POS::Substantive || $next_pos === POS::SubstantiveVerb)
				);
				if (!$is_free_root_after_first &&
					$next_syn !== Synthesis::Suffix &&
					$next_syn !== Synthesis::Participle) {
					return false;
				}
			}
		}
	} elseif ($meaning === Meaning::PARENCO) {
		if ($idx > 0) {
			$prev = $ml->get($idx - 1);
			if ($prev) {
				$m = $prev->morpheme;
				if ($m !== 'bo' && $m !== 'ge' && $m !== 'pra') return false;
			}
		}
		if ($idx < $last) {
			$next = $ml->get($idx + 1);
			if ($next && $next->morpheme !== 'in') return false;
		}
	} elseif (is_animal($meaning)) {
		if ($idx > 0) {
			$prev = $ml->get($idx - 1);
			if ($prev && $prev->synthesis !== Synthesis::Prefix) return false;
		}
		if ($idx < $last) {
			$next = $ml->get($idx + 1);
			if ($next) {
				$m = $next->morpheme;
				// Permetti composti liberi se l'animale è preceduto solo da prefissi
				// (es. kat-am-ant-o, ge-kat-am-ant-o-j)
				$only_prefixes_before = true;
				for ($n = 0; $n < $idx; $n++) {
					$e = $ml->get($n);
					if ($e && $e->synthesis !== Synthesis::Prefix) {
						$only_prefixes_before = false;
						break;
					}
				}
				if (!$only_prefixes_before && $m !== 'in' && $m !== 'id' && $m !== 'aĵ' && $m !== 'ov') return false;
			}
		}
	} elseif ($meaning === Meaning::ETNO) {
		if ($idx > 0) {
			$prev = $ml->get($idx - 1);
			if ($prev && $prev->morpheme !== 'ge') return false;
		}
		if ($idx < $last) {
			$next = $ml->get($idx + 1);
			if ($next) {
				$m = $next->morpheme;
				if ($m !== 'in' && $m !== 'id' && $m !== 'land' && $m !== 'stil') return false;
			}
		}
	}
	return true;
}

function valid_separator(int $pos, int $idx, MorphemeList $ml): bool {
	if ($idx === 0) return false;
	$previous = $ml->get($idx - 1);
	if (!$previous) return false;
	$prev_pos = $previous->part_of_speech;
	$toe		= $ml->type_of_ending();

	if ($pos === POS::Substantive && $prev_pos > POS::Adjective) return false;
	if ($pos === POS::Adjective || $pos === POS::Adverb) {
		if ($toe !== POS::Adjective && $toe !== POS::Adverb) return false;
		if ($prev_pos > POS::Adverb) return false;
	}
	return true;
}

function check_participle(int $idx, MorphemeList $ml): bool {
	if ($idx === 0) return false;
	$last  = $ml->get_last_index();
	$entry = $ml->get($idx);
	if (!$entry) return false;
	$part_str = $entry->morpheme;

	$prev = $ml->get($idx - 1);
	if (!$prev) return false;
	$prev_str   = $prev->morpheme;
	$prev_pos   = $prev->part_of_speech;
	$prev_trans = $prev->transitivity;

	$next_str = null;
	if ($idx < $last) {
		$next = $ml->get($idx + 1);
		if (!$next) return false;
		$next_str = $next->morpheme;
	}

	if ($prev_pos === POS::Verb || $prev_pos === POS::SubstantiveVerb) {
		if (mb_strlen($part_str) === 2) {  // passive: -it, -at, -ot
			if ($prev_trans !== Transitivity::Transitive) return false;
		}
		if ($idx < $last) {
			return in_array($next_str, ['aĵ', 'ul', 'in', 'ec', 'ar'], true);
		}
		return true;
	}

	return in_array($prev_str, ['antaŭ', 'anstataŭ', 'ĉirkaŭ', 'kontraŭ', 'super'], true);
}

/**
 * Main synthesis scanner: called after a word is fully divided into morphemes.
 */
function scan_morphemes(MorphemeList $ml): bool {
	$last = $ml->get_last_index();
	if ($ml->count_separators() > 1) return false;

	for ($idx = 0; $idx <= $last; $idx++) {
		$entry = $ml->get($idx);
		if (!$entry) return false;

		$syn		= $entry->synthesis;
		$pos		= $entry->part_of_speech;
		$morpheme = $entry->morpheme;

		if ($entry->flag === 'separator') {
			if (!valid_separator($pos, $idx, $ml)) return false;
		}

		if ($syn === Synthesis::Prefix) {
			if ($idx === $last) return false;
			if (!check_prefix($morpheme, $idx, $ml)) return false;
		} elseif ($syn === Synthesis::Participle) {
			if (!check_participle($idx, $ml)) return false;
		} elseif ($syn === Synthesis::Limited) {
			if (!check_limited_synthesis($morpheme, $idx, $ml)) return false;
		}  elseif ($syn === Synthesis::Suffix) {
			$has_root = false;
			for ($n = 0; $n < $idx; $n++) {
				$e = $ml->get($n);
				if ($e && ($e->synthesis === Synthesis::UnLimited || $e->synthesis === Synthesis::Limited)) {
					$has_root = true;
					break;
				}
			}
			if (!$has_root) return false;
		}
	}
	return true;
}
