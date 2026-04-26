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
		public bool   $dubious = false;

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
		if (mb_strlen($rest_of_word) === 0)			return false;
		if ($index >= MorphemeList::MAX_MORPHEMES)	return false;

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

		$original_word = x_to_accent(remove_hyphens($original_word));
		$word          = mb_strtolower($original_word);
		$word_length   = mb_strlen($word);

		// Hardcoded pronoun exceptions
		if ($word_length < 5) {
			if ($word === 'ĝin')  return new AnalysisResult($original_word, 'ĝi.n',  true);
			if ($word === 'lin')  return new AnalysisResult($original_word, 'li.n',  true);
			if ($word === 'min')  return new AnalysisResult($original_word, 'mi.n',  true);
			if ($word === 'sin')  return new AnalysisResult($original_word, 'si.n',  true);
			if ($word === 'vin')  return new AnalysisResult($original_word, 'vi.n',  true);
			if ($word === 'cian') return new AnalysisResult($original_word, 'ci.an', true);
			if ($word === 'lian') return new AnalysisResult($original_word, 'li.an', true);
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

		$length         = $word_length - ($ending->length - substr_count($ending->ending, '.'));
		$word_no_ending = mb_substr($word, 0, $length);

		// Simple (non-compound) word
		$entry = $dictionary[$word_no_ending] ?? null;
		if ($entry && $entry->with_ending === WithEnding::Yes) {
			$divided = $entry->morpheme . '.' . $ending->ending;
			return new AnalysisResult($original_word, $divided, true);
		}

		// Compound word: recursive morphological analysis
		$morpheme_list = new MorphemeList($ending);
		$valid         = find_morpheme($word_no_ending, $dictionary, 0, $morpheme_list);

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
			POS::Substantive      => 'Substantiva',
			POS::SubstantiveVerb  => 'Substantivo verba',
			POS::Verb             => 'Verba',
			POS::Adjective        => 'Adjektiva',
			POS::Number           => 'Nombro',
			POS::Adverb           => 'Adverba',
			POS::Pronoun          => 'Pronomo',
			POS::PronounAdjective => 'Pronomo adjektiva',
			POS::Preposition      => 'Preposicia',
			POS::Conjunction      => 'Konjunkcia',
			POS::Subjunction      => 'Subjunkcia',
			POS::Interjection     => 'Interjekcia',
			POS::Prefix           => 'Prefikso',
			POS::TechPrefix       => 'Teknika prefikso',
			POS::Suffix           => 'Sufikso',
			POS::Article          => 'Artikolo',
			POS::Participle       => 'Participa',
			POS::Abbreviation     => 'Mallongigo',
			POS::Letter           => 'Litera',
		];
		return $map[$pos] ?? 'Nekonata';
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
	// Internal: aggiunge morfemi analizzati a $result, spezzando le voci
	// precompilate con punti (es. 'mal.plej') nei loro componenti.
	// $dubious = true aggiunge '?' al type per radici non verificate.
	// ---------------------------------------------------------------------------
	function _add_analyzed_morphemes(
		array  &$result,
		string  $morpheme,
		int     $pos,
		string  $defaultType,
		bool    $dubious = false
	): void {
		$prefixes = ['bo','dis','ek','eks','fi','ge','mal','mis','pra','re'];
		$suffixes = [
			'ad','aĵ','an','ar','ebl','ec','eg','ej','em','end','er','estr',
			'et','id','ig','iĝ','il','in','ind','ing','ism','ist','obl',
			'on','op','uj','ul','um'
		];

		foreach (explode('.', $morpheme) as $part) {
			$low = mb_strtolower($part);
			if (in_array($low, $prefixes, true)) {
				$type = 'prefikso';
			} elseif (in_array($low, $suffixes, true)) {
				$type = 'sufikso';
			} else {
				$type = $dubious ? $defaultType . '?' : $defaultType;
			}
			$result[] = ['morpheme' => $part, 'pos' => pos_label($pos), 'type' => $type];
		}
	}

	function _morphemes_from_without_ending(EspDictEntry $e, array $dictionary): array {
		$result = [];
		$parts  = explode('.', $e->morpheme);
		$last   = count($parts) - 1;

		// Tutte le parti tranne l'ultima → radice con POS corretto
		for ($i = 0; $i < $last; $i++) {
			$stem_entry = $dictionary[$parts[$i]] ?? null;
			$stem_pos   = $stem_entry ? pos_label($stem_entry->part_of_speech) : pos_label($e->part_of_speech);
			$result[] = [
				'morpheme' => $parts[$i],
				'pos'      => $stem_pos,
				'type'     => 'radiko',
			];
		}

		// L'ultima parte: scomponi desinenza + plurale + accusativo
		$fin = $parts[$last];
		$havasAkuzativon = false;
		$havasPluralon   = false;
		if (mb_substr($fin, -1) === 'n') { $havasAkuzativon = true; $fin = mb_substr($fin, 0, -1); }
		if (mb_substr($fin, -1) === 'j') { $havasPluralon   = true; $fin = mb_substr($fin, 0, -1); }

		if ($fin !== '') {
			switch ($fin) {
				case 'a':  $fin_pos = 'Adjektiva';   break;
				case 'e':  $fin_pos = 'Adverba';     break;
				case 'o':  $fin_pos = 'Substantiva'; break;
				case 'i':  $fin_pos = 'Verba';       break;
				default:   $fin_pos = pos_label($e->part_of_speech); break;
			}
			$result[] = ['morpheme' => $fin, 'pos' => $fin_pos, 'type' => 'finaĵo'];
		}
		if ($havasPluralon)   $result[] = ['morpheme' => 'j', 'pos' => 'plurala',   'type' => 'pluralo'];
		if ($havasAkuzativon) $result[] = ['morpheme' => 'n', 'pos' => 'akuzativa', 'type' => 'akuzativo'];

		return $result;
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
			return [['morpheme' => $original_word, 'pos' => 'Litera', 'type' => 'radiko']];
		}
	 
		// Pronoun exceptions
		$conv = x_to_accent(mb_strtolower(remove_hyphens($original_word)));
		$pronouns = ['ĝin'=>['ĝi','n'],'lin'=>['li','n'],'min'=>['mi','n'],
					 'sin'=>['si','n'],'vin'=>['vi','n'],'lian'=>['li','an'],'cian'=>['ci','an']];
		if (isset($pronouns[$conv])) {
			[$stem, $acc] = $pronouns[$conv];
			return [
				['morpheme' => $stem, 'pos' => 'Pronomo',    'type' => 'radiko'],
				['morpheme' => $acc,  'pos' => 'Akuzativo',  'type' => 'akuzativa'],
			];
		}
	 
		$data = _analyse_word($original_word, $dictionary);
		if ($data === null) return null;
	 
		$result = [];
	 
		// type helper based on Synthesis constant
		$syn_type = function(int $syn, int $pos): string {
			switch ($syn) {
				case Synthesis::Prefix:     return $pos === POS::Preposition ? 'preposicio' : 'prefikso';
				case Synthesis::Suffix:     return 'sufikso';
				case Synthesis::Participle: return 'participo';
				default:                    return 'radiko';
			}
		};

		if ($data['without_ending']) {
			$e = $data['simple_entry'];
			if (strpos($e->morpheme, '.') !== false) {
				return _morphemes_from_without_ending($e, $dictionary);
			}
			_add_analyzed_morphemes($result, $e->morpheme, $e->part_of_speech, 'radiko');
			return $result;
		}

		if ($data['simple_entry'] !== null) {
			// Parola semplice (es: am.ind.um.o -> am.ind.um)
			$e = $data['simple_entry'];
			_add_analyzed_morphemes($result, $e->morpheme, $e->part_of_speech, 'radiko');
		} else {
			// Parola composta: scorre la MorphemeList
			$ml = $data['morpheme_list'];
			for ($i = 0; $i <= $ml->get_last_index(); $i++) {
				$e = $ml->get($i);
				if ($e === null) continue;
				if ($e->flag === 'separator') {
					$result[] = ['morpheme' => $e->morpheme, 'pos' => pos_label($e->part_of_speech), 'type' => 'disigilo'];
				} else {
					// Analizziamo il morfema della lista (nel caso contenesse punti)
					_add_analyzed_morphemes($result, $e->morpheme, $e->part_of_speech, $syn_type($e->synthesis, $e->part_of_speech));
				}
			}
		}
		
		// Grammatical ending
		$ending = $data['ending'];
		$finajxo = $ending->ending;
		$havasAkuzativon = false;
		if (mb_substr($finajxo, -1) == 'n') {
			$havasAkuzativon = true;
			$finajxo = mb_substr($finajxo, 0, -1);
		}
		$havasPluralon = false;
		if (mb_substr($finajxo, -1) == 'j') {
			$havasPluralon = true;
			$finajxo = mb_substr($finajxo, 0, -1);
		}
		$result[] = ['morpheme' => $finajxo, 'pos' => pos_label($ending->part_of_speech), 'type' => 'finaĵo'];
		if ($havasPluralon) $result[] = ['morpheme' => 'j', 'pos' => 'plurala', 'type' => 'plurala'];
		if ($havasAkuzativon) $result[] = ['morpheme' => 'n', 'pos' => 'akuzativa', 'type' => 'akuzativo'];
	 
		return $result;
	}

	// ---------------------------------------------------------------------------
	// Multi-solution support
	// ---------------------------------------------------------------------------

	/**
	 * Variante di check_synthesis che accumula tutte le soluzioni valide.
	 * @param MorphemeList[] &$results
	 */
	function _check_synthesis_all(
		string       $rest_of_word,
		array        $dictionary,
		int          $index,
		MorphemeList $morpheme_list,
		bool         $last_morpheme,
		array        &$results
	): void {
		$entry = $morpheme_list->get($index);
		if (!$entry) return;

		if ($entry->synthesis === Synthesis::Suffix &&
			!check_suffix($entry->morpheme, $index, $morpheme_list)) {
			return;
		}

		if (!$last_morpheme) {
			_find_all_morphemes($rest_of_word, $dictionary, $index + 1, $morpheme_list, $results);
			return;
		}

		if (scan_morphemes($morpheme_list)) {
			$results[] = clone $morpheme_list;
		}
	}

	/**
	 * Variante di find_morpheme che raccoglie TUTTE le MorphemeList valide.
	 * L'originale find_morpheme resta intatta.
	 * @param MorphemeList[] &$results
	 */
	function _find_all_morphemes(
		string       $rest_of_word,
		array        $dictionary,
		int          $index,
		MorphemeList $morpheme_list,
		array        &$results
	): void {
		if (mb_strlen($rest_of_word) === 0)        return;
		if ($index >= MorphemeList::MAX_MORPHEMES)  return;

		// Prova la stringa intera come morfema singolo (non per index 0)
		if ($index > 0) {
			$entry = $dictionary[$rest_of_word] ?? null;
			if ($entry && $entry->synthesis !== Synthesis::No) {
				$copy = clone $morpheme_list;
				$copy->put($index, $entry);
				_check_synthesis_all($rest_of_word, $dictionary, $index, $copy, true, $results);
			}
		}

		$len        = mb_strlen($rest_of_word);
		$max_length = $len - 2;

		for ($size = $max_length; $size >= 2; $size--) {
			$morpheme = mb_substr($rest_of_word, 0, $size);
			$entry    = $dictionary[$morpheme] ?? null;
			if ($entry && $entry->synthesis !== Synthesis::No) {
				$copy = clone $morpheme_list;
				$copy->put($index, $entry);
				_check_synthesis_all(
					mb_substr($rest_of_word, $size),
					$dictionary, $index, $copy, false, $results
				);
			}
		}

		// Vocale separatrice
		if ($index === 0 || $len < 3) return;
		$sep_entry = EspDictEntry::new_separator(mb_substr($rest_of_word, 0, 1));
		if ($sep_entry) {
			$copy = clone $morpheme_list;
			$copy->put($index, $sep_entry);
			_check_synthesis_all(
				mb_substr($rest_of_word, 1),
				$dictionary, $index, $copy, false, $results
			);
		}
	}

	/**
	 * Restituisce true se tutti i morfemi della MorphemeList esistono nel dizionario.
	 */
	function _all_morphemes_exist(MorphemeList $ml, array $dictionary): bool {
		for ($i = 0; $i <= $ml->get_last_index(); $i++) {
			$e = $ml->get($i);
			if (!$e) return false;
			if ($e->flag === 'separator') continue;
			// Le voci precompilate (flag=K) contengono punti nel morfema —
			// verifichiamo ogni pezzo separatamente
			if ($e->flag === 'K') continue;
			foreach (explode('.', $e->morpheme) as $part) {
				$key = mb_strtolower($part);
				if (!isset($dictionary[$key])) return false;
			}
		}
		return true;
	}

	/**
	 * Calcola un punteggio per ordinare le soluzioni:
	 * prima le radici più lunghe (lunghezza totale decrescente),
	 * poi per rarità crescente (morfemi più comuni prima).
	 * Restituisce [lunghezza_totale_radici, rarità_totale].
	 */
	function _solution_score(MorphemeList $ml): array {
		$root_count   = 0;
		$total_count  = 0;
		$total_rarity = 0;
		for ($i = 0; $i <= $ml->get_last_index(); $i++) {
			$e = $ml->get($i);
			if (!$e || $e->flag === 'separator') continue;
			$total_count++;
			$total_rarity += $e->rarity;
			if ($e->synthesis === Synthesis::UnLimited ||
				$e->synthesis === Synthesis::Limited) {
				$root_count++;
			}
		}
		return [$root_count, $total_count, $total_rarity];
	}
	
	/**
	 * Restituisce tutte le analisi valide di una parola come array di AnalysisResult.
	 *
	 * Logica di filtro:
	 * - Se c'è più di una soluzione, tiene solo quelle dove tutti i morfemi
	 *   esistono nel dizionario (esclude radici inventate tipo 'buterp').
	 * - Se dopo il filtro resta una sola soluzione (o ce n'era già una sola),
	 *   la propone comunque, marcando le radici dubbie con '?' nel type.
	 *
	 * Ordinamento: radici più lunghe prima, poi rarità crescente.
	 *
	 * @param EspDictEntry[] $dictionary
	 * @return AnalysisResult[]
	 */
	function check_word_all(string $original_word, array $dictionary): array {
		// Casi con soluzione unica garantita: delega a check_word
		$len = mb_strlen($original_word);
		if ($len === 1) {
			$r = check_word($original_word, $dictionary);
			return $r->valid ? [$r] : [];
		}
		if ($len > 2) {
			$chars = mb_str_split($original_word);
			if (is_hyphen($chars[1])) {
				$r = check_word($original_word, $dictionary);
				return $r->valid ? [$r] : [];
			}
		}

		$original_word = x_to_accent(remove_hyphens($original_word));
		$word          = mb_strtolower($original_word);
		$word_length   = mb_strlen($word);

		// Pronomi e parole senza desinenza
		$pronouns = ['ĝin','lin','min','sin','vin','lian','cian'];
		if (in_array($word, $pronouns, true)) {
			$r = check_word($original_word, $dictionary);
			return $r->valid ? [$r] : [];
		}
		$entry = $dictionary[$word] ?? null;
		if ($entry && $entry->without_ending === WithoutEnding::Yes) {
			$r = check_word($original_word, $dictionary);
			return $r->valid ? [$r] : [];
		}

		$ending = get_ending($word);
		if ($ending === null) return [];

		$word_no_ending = mb_substr($word, 0, $word_length - $ending->length);

		// Parola semplice: soluzione unica
		$entry = $dictionary[$word_no_ending] ?? null;
		if ($entry && $entry->with_ending === WithEnding::Yes) {
			$r = check_word($original_word, $dictionary);
			return $r->valid ? [$r] : [];
		}

		// Parola composta: raccoglie tutte le soluzioni
		$ml      = new MorphemeList($ending);
		$raw     = [];
		_find_all_morphemes($word_no_ending, $dictionary, 0, $ml, $raw);

		if (empty($raw)) return [];

		// Filtra: tieni solo quelle con tutti i morfemi nel dizionario
		$valid = array_filter($raw, fn($m) => _all_morphemes_exist($m, $dictionary));

		// Se il filtro elimina tutto, usa tutte le soluzioni (marcate dubbie)
		$dubious = empty($valid);
		$pool    = $dubious ? $raw : array_values($valid);

		// Ordinamento
		usort($pool, function($a, $b) {
			[$ra, $ca, $va] = _solution_score($a);
			[$rb, $cb, $vb] = _solution_score($b);
			if ($ca !== $cb) return $ca - $cb; // meno morfemi totali prima
			if ($ra !== $rb) return $ra - $rb; // meno radici prima
			return $va - $vb;                  // rarità crescente
		});

		// Deduplica per display_form e costruisce AnalysisResult
		$seen   = [];
		$unique = [];
		foreach ($pool as $m) {
			$form = $m->display_form();
			if (!isset($seen[$form])) {
				$seen[$form]  = true;
				$r            = new AnalysisResult($original_word, $form, true);
				$r->dubious   = $dubious;
				$unique[]     = $r;
			}
		}
		return $unique;
	}

	/**
	 * Converte una MorphemeList + Ending in array di morfemi,
	 * usando _add_analyzed_morphemes (stessa logica di check_word_morphemes).
	 * $dubious = true aggiunge '?' alle radici.
	 */
	function _morpheme_list_to_morphemes(
		MorphemeList $ml,
		Ending       $ending,
		bool         $dubious = false
	): array {
		$syn_type = function(int $syn, int $pos): string {
			switch ($syn) {
				case Synthesis::Prefix:     return $pos === POS::Preposition ? 'preposicio' : 'prefikso';
				case Synthesis::Suffix:     return 'sufikso';
				case Synthesis::Participle: return 'participo';
				default:                    return 'radiko';
			}
		};

		$result = [];
		for ($i = 0; $i <= $ml->get_last_index(); $i++) {
			$e = $ml->get($i);
			if (!$e) continue;
			if ($e->flag === 'separator') {
				$result[] = ['morpheme' => $e->morpheme,
							 'pos'      => pos_label($e->part_of_speech),
							 'type'     => 'disigilo'];
			} else {
				_add_analyzed_morphemes(
					$result, $e->morpheme, $e->part_of_speech,
					$syn_type($e->synthesis, $e->part_of_speech), $dubious
				);
			}
		}

		// Desinenza
		$fin             = $ending->ending;
		$havasAkuzativon = false;
		$havasPluralon   = false;
		if (mb_substr($fin, -1) === 'n') { $havasAkuzativon = true; $fin = mb_substr($fin, 0, -1); }
		if (mb_substr($fin, -1) === 'j') { $havasPluralon   = true; $fin = mb_substr($fin, 0, -1); }
		$result[] = ['morpheme' => $fin, 'pos' => pos_label($ending->part_of_speech), 'type' => 'finaĵo'];
		if ($havasPluralon)   $result[] = ['morpheme' => 'j', 'pos' => 'plurala',   'type' => 'pluralo'];
		if ($havasAkuzativon) $result[] = ['morpheme' => 'n', 'pos' => 'akuzativa', 'type' => 'akuzativo'];

		return $result;
	}

	/**
	 * Restituisce tutte le analisi valide come array di array-morfemi,
	 * nello stesso formato di check_word_morphemes().
	 *
	 * @param EspDictEntry[] $dictionary
	 * @return array[]   array di soluzioni, ciascuna è un array di morfemi
	 */
	function check_word_morphemes_all(string $original_word, array $dictionary): array {
		// Caso lettera singole: usa check_word_morphemes direttamente
		$len = mb_strlen($original_word);
		if ($len === 1) {
			$m = check_word_morphemes($original_word, $dictionary);
			return $m ? [$m] : [];
		}

		// Caso pronome: usa check_word_morphemes direttamente
		$conv = x_to_accent(mb_strtolower(remove_hyphens($original_word)));
		$pronouns = ['ĝin','lin','min','sin','vin','lian','cian'];
		if (in_array($conv, $pronouns, true)) {
			$m = check_word_morphemes($original_word, $dictionary);
			return $m ? [$m] : [];
		}
		// Parole senza desinenza (pronomi possessivi, aggettivi pronominali, ecc.)
		$entry = $dictionary[$conv] ?? null;
		if ($entry && $entry->without_ending === WithoutEnding::Yes) {
			$m = check_word_morphemes($original_word, $dictionary);
			return $m ? [$m] : [];
		}

		// Casi generali: usa check_word_all per avere tutte le soluzioni
		// con il flag dubious già calcolato
		$all_results = check_word_all($original_word, $dictionary);
		if (empty($all_results)) return [];

		// Per ogni AnalysisResult dobbiamo ricostruire la MorphemeList.
		// Rieseguiamo _find_all_morphemes per avere le liste grezze,
		// poi le abbiniamo alle display_form già calcolate.
		$word        = x_to_accent(remove_hyphens(mb_strtolower($original_word)));
		$word_length = mb_strlen($word);
		$ending      = get_ending($word);
		if ($ending === null) {
			// Parola senza desinenza o semplice: una sola soluzione
			$m = check_word_morphemes($original_word, $dictionary);
			return $m ? [$m] : [];
		}

		$word_no_ending = mb_substr($word, 0, $word_length - $ending->length);
		$entry          = $dictionary[$word_no_ending] ?? null;
		if ($entry && $entry->with_ending === WithEnding::Yes) {
			$m = check_word_morphemes($original_word, $dictionary);
			return $m ? [$m] : [];
		}

		// Rieseguiamo _find_all_morphemes per avere le MorphemeList grezze
		$ml_base = new MorphemeList($ending);
		$raw     = [];
		_find_all_morphemes($word_no_ending, $dictionary, 0, $ml_base, $raw);

		// Costruiamo un indice display_form → MorphemeList
		$by_form = [];
		foreach ($raw as $ml) {
			$form = mb_strtolower($ml->display_form(), 'UTF-8');
			if (!isset($by_form[$form])) $by_form[$form] = $ml;
		}

		$dubious = $all_results[0]->dubious ?? false;
		$output  = [];
		foreach ($all_results as $r) {
			$ml = $by_form[mb_strtolower($r->word, 'UTF-8')] ?? null;
			if ($ml === null) continue;
			$morphemes = _morpheme_list_to_morphemes($ml, $ending, $dubious);
			
			// Ripristina maiuscole sul primo morfema
			$full = implode('', array_column($morphemes, 'morpheme'));
			$restored = restore_capitals($original_word, $full);
			$offset = 0;
			foreach ($morphemes as &$m) {
				$len = mb_strlen($m['morpheme'], 'UTF-8');
				$m['morpheme'] = mb_substr($restored, $offset, $len, 'UTF-8');
				$offset += $len;
			}
			unset($m);
			
			$output[] = $morphemes;
		}

		return $output;
	}			
?>