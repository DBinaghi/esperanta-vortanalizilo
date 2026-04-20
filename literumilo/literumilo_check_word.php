<?php
	// literumilo_check_word.php
	// Main spell-checking and morphological analysis logic.
	// Translated from Python by Claude. Original author: Klivo Lendon.

	require_once __DIR__ . '/literumilo_entry.php';
	require_once __DIR__ . '/literumilo_ending.php';
	require_once __DIR__ . '/literumilo_suffix.php';
	require_once __DIR__ . '/literumilo_morpheme_list.php';
	require_once __DIR__ . '/literumilo_scan_morphemes.php';
	require_once __DIR__ . '/literumilo_utils.php';
	require_once __DIR__ . '/literumilo_load.php';

	class AnalysisResult {
		public string $word;
		public bool   $valid;

		public function __construct(string $original, string $word, bool $valid) {
			$this->word  = restore_capitals($original, $word);
			$this->valid = $valid;
		}
	}

	/**
	 * Checks synthesis after a morpheme has been found.
	 * Mirrors Python's check_synthesis().
	 *
	 * @param EspDictEntry[] $dictionary
	 */
	function check_synthesis(string $rest_of_word, array $dictionary, int $index, MorphemeList $morpheme_list, bool $last_morpheme): bool 
	{
		$entry = $morpheme_list->get($index);
		if (!$entry) return false;

		$syn   = $entry->synthesis;
		$morph = $entry->morpheme;

		if ($syn === Synthesis::Suffix && !check_suffix($morph, $index, $morpheme_list)) {
			return false;
		}

		if (!$last_morpheme) {
			return find_morpheme($rest_of_word, $dictionary, $index + 1, $morpheme_list);
		}

		// Last morpheme: validate all prefixes and limited morphemes.
		return scan_morphemes($morpheme_list);
	}

	/**
	 * Recursively divides a word into morphemes while checking synthesis.
	 * Mirrors Python's find_morpheme().
	 *
	 * @param EspDictEntry[] $dictionary
	 */
	function find_morpheme(string $rest_of_word, array $dictionary, int	$index,	MorphemeList $morpheme_list): bool 
	{
		if (mb_strlen($rest_of_word) === 0)			 return false;
		if ($index >= MorphemeList::MAX_MORPHEMES)	  return false;

		// Try the whole remaining string as a single morpheme (not for index 0).
		if ($index > 0) {
			$entry = $dictionary[$rest_of_word] ?? null;
			if ($entry && $entry->synthesis !== Synthesis::No) {
				$morpheme_list->put($index, $entry);
				if (check_synthesis($rest_of_word, $dictionary, $index, $morpheme_list, true)) {
					return true;
				}
			}
		}

		$length_of_word = mb_strlen($rest_of_word);
		$min_length	 = 2;
		$max_length	 = $length_of_word - 2;

		for ($size = $max_length; $size >= $min_length; $size--) {
			$morpheme = mb_substr($rest_of_word, 0, $size);
			$entry	= $dictionary[$morpheme] ?? null;
			if ($entry && $entry->synthesis !== Synthesis::No) {
				$rest2 = mb_substr($rest_of_word, $size);
				$morpheme_list->put($index, $entry);
				if (check_synthesis($rest2, $dictionary, $index, $morpheme_list, false)) {
					return true;
				}
			}
		}

		// Try a separator vowel between morphemes ('o', 'a', 'e').
		if ($index === 0 || $length_of_word < 3) return false;
		$sep_char  = mb_substr($rest_of_word, 0, 1);
		$sep_entry = EspDictEntry::new_separator($sep_char);
		if ($sep_entry) {
			$morpheme_list->put($index, $sep_entry);
			$rest2 = mb_substr($rest_of_word, 1);
			if (check_synthesis($rest2, $dictionary, $index, $morpheme_list, false)) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Checks whether a word is correctly spelled Esperanto.
	 * Returns an AnalysisResult with the morpheme-divided form and validity flag.
	 *
	 * @param string		 $original_word
	 * @param EspDictEntry[] $dictionary	Pass the array returned by load_dictionary().
	 */
	function check_word(string $original_word, array $dictionary): AnalysisResult {
		$len = mb_strlen($original_word);

		// Single character
		if ($len === 1) {
			$valid = is_word_char($original_word);
			return new AnalysisResult($original_word, $original_word, $valid);
		}

		// Abbreviations like n-ro, s-ino
		if ($len > 2) {
			$chars = mb_str_split($original_word);
			if (is_hyphen($chars[1])) {
				$entry = $dictionary[$original_word] ?? null;
				if ($entry) return new AnalysisResult($original_word, $entry->morpheme, true);
				return new AnalysisResult($original_word, $original_word, false);
			}
		}

		$original_word	= x_to_accent(remove_hyphens($original_word));
		$word			= mb_strtolower($original_word);
		$word_length	= mb_strlen($word);

		// Hardcoded pronoun exceptions
		if ($word_length < 5) {
			if ($word === 'ĝin')  return new AnalysisResult($original_word, 'ĝi.n',  true);
			if ($word === 'lin')  return new AnalysisResult($original_word, 'li.n',  true);
			if ($word === 'min')  return new AnalysisResult($original_word, 'mi.n',  true);
			if ($word === 'sin')  return new AnalysisResult($original_word, 'si.n',  true);
			if ($word === 'vin')  return new AnalysisResult($original_word, 'vi.n',  true);
			if ($word === 'lian') return new AnalysisResult($original_word, 'li.an', true);
			if ($word === 'cian') return new AnalysisResult($original_word, 'ci.an', true);
		}

		// Words that are valid without a grammatical ending (e.g. 'ne', 'post')
		$entry = $dictionary[$word] ?? null;
		if ($entry && $entry->without_ending === WithoutEnding::Yes) {
			return new AnalysisResult($original_word, $entry->morpheme, true);
		}

		$ending = get_ending($word);
		if ($ending === null) {
			return new AnalysisResult($original_word, $word, false);
		}

		$length = $word_length - ($ending->length - substr_count($ending->ending, '.'));
		$word_no_ending = mb_substr($word, 0, $length);

		// Simple (non-compound) word
		$entry = $dictionary[$word_no_ending] ?? null;
		if ($entry && $entry->with_ending === WithEnding::Yes) {
			$divided = $entry->morpheme . '.' . $ending->ending;
			return new AnalysisResult($original_word, $divided, true);
		}

		// Compound word: recursive morphological analysis
		$morpheme_list = new MorphemeList($ending);
		$valid = find_morpheme($word_no_ending, $dictionary, 0, $morpheme_list);

		if ($valid) {
			return new AnalysisResult($original_word, $morpheme_list->display_form(), true);
		}

		return new AnalysisResult($original_word, $word, false);
	}

	// ---------------------------------------------------------------------------
	// POS integer → human-readable label
	// ---------------------------------------------------------------------------
	 
	function pos_label(int $pos): string {
		static $map = [
			POS::Substantive      => 'Substantivo',
			POS::SubstantiveVerb  => 'Substantivo verba',
			POS::Verb             => 'Verbo',
			POS::Adjective        => 'Adjektivo',
			POS::Number           => 'Nombro',
			POS::Adverb           => 'Adverbo',
			POS::Pronoun          => 'Pronomo',
			POS::PronounAdjective => 'Pronomo adjektiva',
			POS::Preposition      => 'Preposicio',
			POS::Conjunction      => 'Conjunkcio',
			POS::Subjunction      => 'Subjunkcio',
			POS::Interjection     => 'Interjekcio',
			POS::Prefix           => 'Prefikso',
			POS::TechPrefix       => 'Teknika prefikso',
			POS::Suffix           => 'Sufikso',
			POS::Article          => 'Artikolo',
			POS::Participle       => 'Participo',
			POS::Abbreviation     => 'Mallongigo',
			POS::Letter           => 'Litero',
		];
		return $map[$pos] ?? 'Unknown';
	}
	 
	// ---------------------------------------------------------------------------
	// Internal: analyse a word and return the raw MorphemeList + Ending,
	// or null if the word is invalid.
	// Returns ['morpheme_list' => MorphemeList, 'ending' => Ending|null,
	//          'simple_entry' => EspDictEntry|null, 'without_ending' => bool]
	// ---------------------------------------------------------------------------
	 
	function _analyse_word(string $original_word, array $dictionary): ?array {
		$len = mb_strlen($original_word);
		if ($len === 1) return null;

		if ($len > 2) {
			$chars = mb_str_split($original_word);
			if (is_hyphen($chars[1])) return null; // abbreviation, skip
		}
	 
		$original_word = x_to_accent(remove_hyphens($original_word));
		$word          = mb_strtolower($original_word);
		$word_length   = mb_strlen($word);
	 
		// Pronoun exceptions — return as-is (single fake morpheme)
		if ($word_length < 5) {
			$pronouns = ['ĝin'=>'ĝi.n','lin'=>'li.n','min'=>'mi.n',
						 'sin'=>'si.n','vin'=>'vi.n','lian'=>'li.an','cian'=>'ci.an'];
			if (isset($pronouns[$word])) return null; // handled by check_word
		}
	 
		// Without ending (e.g. 'ne', 'post')
		$entry = $dictionary[$word] ?? null;
		if ($entry && $entry->without_ending === WithoutEnding::Yes) {
			return ['morpheme_list' => null, 'ending' => null,
					'simple_entry' => $entry, 'without_ending' => true];
		}

		$ending = get_ending($word);
		if ($ending === null) return null;

		$word_no_ending = mb_substr($word, 0, $word_length - $ending->length);
	 
		// Simple word
		$entry = $dictionary[$word_no_ending] ?? null;
		if ($entry && $entry->with_ending === WithEnding::Yes) {
			return ['morpheme_list' => null, 'ending' => $ending,
					'simple_entry' => $entry, 'without_ending' => false];
		}
	 
		// Compound word
		$morpheme_list = new MorphemeList($ending);
		$valid = find_morpheme($word_no_ending, $dictionary, 0, $morpheme_list);
		if (!$valid) return null;
	 
		return ['morpheme_list' => $morpheme_list, 'ending' => $ending,
				'simple_entry' => null, 'without_ending' => false];
	}
	 
	// ---------------------------------------------------------------------------
	// Public: returns a structured array of morphemes, or null if word is invalid.
	// Each element: ['morpheme' => string, 'pos' => string, 'type' => string]
	// 'type' is one of: prefix, root, suffix, participle, ending, separator
	// ---------------------------------------------------------------------------
	 
	function check_word_morphemes(string $original_word, array $dictionary): ?array {
		$len = mb_strlen($original_word);
	 
		// Single character
		if ($len === 1) {
			if (!is_word_char($original_word)) return null;
			return [['morpheme' => $original_word, 'pos' => 'Letter', 'type' => 'radiko']];
		}
	 
		// Pronoun exceptions
		$conv = x_to_accent(mb_strtolower(remove_hyphens($original_word)));
		$pronouns = ['ĝin'=>['ĝi','n'],'lin'=>['li','n'],'min'=>['mi','n'],
					 'sin'=>['si','n'],'vin'=>['vi','n'],'lian'=>['li','an'],'cian'=>['ci','an']];
		if (isset($pronouns[$conv])) {
			[$stem, $acc] = $pronouns[$conv];
			return [
				['morpheme' => $stem, 'pos' => 'Pronoun',    'type' => 'radiko'],
				['morpheme' => $acc,  'pos' => 'Accusative',  'type' => 'akuzativo'],
			];
		}
	 
		$data = _analyse_word($original_word, $dictionary);
		if ($data === null) return null;
	 
		$result = [];
	 
		// type helper based on Synthesis constant
		$syn_type = function(int $syn): string {
			switch ($syn) {
				case Synthesis::Prefix:     return 'prefikso';
				case Synthesis::Suffix:     return 'sufikso';
				case Synthesis::Participle: return 'participo';
				case Synthesis::Limited:
				case Synthesis::UnLimited:  return 'radiko';
				default:                    return 'radiko';
			}
		};
	 
		if ($data['without_ending']) {
			// Word like 'ne', 'post' — no grammatical ending
			$e = $data['simple_entry'];
			$result[] = ['morpheme' => $e->morpheme, 'pos' => pos_label($e->part_of_speech), 'type' => 'radiko'];
			return $result;
		}
	 
		if ($data['simple_entry'] !== null) {
			// Simple non-compound word
			$e = $data['simple_entry'];
			$result[] = ['morpheme' => $e->morpheme, 'pos' => pos_label($e->part_of_speech), 'type' => 'radiko'];
		} else {
			// Compound word: walk the MorphemeList
			$ml   = $data['morpheme_list'];
			$last = $ml->get_last_index();
			for ($i = 0; $i <= $last; $i++) {
				$e = $ml->get($i);
				if ($e === null) continue;
				if ($e->flag === 'separator') {
					$result[] = ['morpheme' => $e->morpheme, 'pos' => pos_label($e->part_of_speech), 'type' => 'disigilo'];
				} else {
					$result[] = ['morpheme' => $e->morpheme, 'pos' => pos_label($e->part_of_speech), 'type' => $syn_type($e->synthesis)];
				}
			}
		}

		// Grammatical ending
		$ending = $data['ending'];
		$finajxo = $ending->ending;
		$isAkuzativo = false;
		if (mb_substr($finajxo, -1) == 'n') {
			$isAkuzativo = true;
			$finajxo = mb_substr($finajxo, 0, mb_strlen($finajxo) - 1);
		}
		$isPluralo = false;
		if (mb_substr($finajxo, -1) == 'j') {
			$isPluralo = true;
			$finajxo = mb_substr($finajxo, 0, mb_strlen($finajxo) - 1);
		}
		$result[] = ['morpheme' => $finajxo, 'pos' => pos_label($ending->part_of_speech), 'type' => 'finaĵo'];
		if ($isPluralo) $result[] = ['morpheme' => 'j', 'pos' => 'pluralo', 'type' => 'pluralo'];
		if ($isAkuzativo) $result[] = ['morpheme' => 'n', 'pos' => 'akuzativo', 'type' => 'akuzativo'];
	 
		return $result;
	}
?>
