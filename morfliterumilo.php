<?php
	require_once 'literumilo/literumilo.php';

	if (isset($_POST['parola'])) $testo_da_analizzare = $_POST['parola'];

	// $r = literumilo_check_word("miskomprenita");
	// $r->valid = true
	// $r->word  = "mis.kompren.it.a"

	// $out = literumilo_analyze_string($testo, true);  // modalità morfemi
	// $unk = literumilo_analyze_string($testo, false); // modalità spell-check
?>
<!DOCTYPE html>
<html lang="eo">
	<head>
		<meta charset="UTF-8">
		<meta name="viewport" content="width=device-width, initial-scale=1.0">
		<title>Esperanta morfanalizilo 2.0</title>
		<style>
			body { font-family: sans-serif; line-height: 1.6; max-width: 800px; margin: 20px auto; padding: 0 15px; color: #333; }
			.container { background: #f9f9f9; padding: 20px; border-radius: 8px; box-shadow: 0 2px 5px rgba(0,0,0,0.1); }
			h1 { color: #2e7d32; }
			
			/* Stile del Form */
			.search-box { display: flex; gap: 10px; margin-bottom: 15px; }
			input[type="text"] { flex: 1; padding: 10px; border: 1px solid #ccc; border-radius: 4px; font-size: 16px; }
			button { background-color: #4CAF50; color: white; padding: 10px 20px; border: none; border-radius: 4px; cursor: pointer; font-size: 16px; font-weight: bold; }
			button:hover { background-color: #45a049; }
			
			/* Stile Risultati */
			.risultato { background: white; padding: 15px; border-left: 5px solid #4CAF50; margin-bottom: 10px; border-radius: 4px; }
			.scomposizione:not(:last-child) { margin-bottom: 1em; }
			.esempio-titolo { margin-top: 30px; font-weight: bold; color: #666; border-bottom: 1px solid #ddd; }
			.footer-info { font-size: 0.8em; color: #777; margin-top: 10px; }


			.descrizione { font-weight:bold; margin-bottom: .5em; font-family:sans-serif; }
			.analizero { font-size:1.2em; font-weight:bold; display:block; margin-bottom: 2px; }

			/* Stile base dei blocchetti */
			.morfemo {
				display: inline-block;
				vertical-align: top;
				margin-right: .3em;
				margin-bottom: .3em;
				text-align: center;
				font-family: 'Segoe UI', Tahoma, sans-serif;
				font-size: 1em;
				border-width: 1px;
				border-style: solid;
				border-radius: 4px;
				padding: 4px 6px;
				box-shadow: 0 1px 2px rgba(0,0,0,0.05); /* Leggera profondità */
			}
			
			.morfemo.prefikso		{ background-color: #fef3c7; border-color: #f59e0b; color: #92400e; } /* Giallo miele */
			.morfemo.radiko       { background-color: #dcfce7; border-color: #22c55e; color: #166534; } /* Verde salvia */
			.morfemo.sufikso     { background-color: #dbeafe; border-color: #3b82f6; color: #1e40af; } /* Blu pastello */
			.morfemo.participo,.morfemo.pluralo { background-color: #fee2e2; border-color: #ef4444; color: #991b1b; } /* Rosso rosa */
			.morfemo.finaĵo     { background-color: #ffedd5; border-color: #f97316; color: #9a3412; } /* Arancio tenue */
			.morfemo.disigilo,.morfemo.akuzativo  { background-color: #fecaca; border-color: #dc2626; color: #7f1d1d; } /* Rosso profondo */
			.etikedo 			{ display: block; font-size: 0.65em; opacity: 0.7; }
		</style>
	</head>

	<body>
		<div class="container">
			<h1>Esperanta morfanalizilo 2.0</h1>

			<form method="POST" action="" class="search-box">
				<input type="text" name="parola" placeholder="Enigu vorton (ekz.: malsanulejon)" value="<?php echo $testo_da_analizzare ?? '' ?>">
				<button type="submit">Analizi</button>
			</form>

			<div class="content">
				<?php if (isset($testo_da_analizzare) && !empty($testo_da_analizzare)): ?>
					<div class="risultato">
						<?php 
							if (strpos($testo_da_analizzare, ' ') !== false) {
								echo literumilo_analyze_string($testo_da_analizzare, true);
							} else {
								$html = literumilo_check_word_html($testo_da_analizzare);
								echo $html ?? "<span class='invalid'>?</span>";
							}
						?>
					</div>
					<p><a href="?">← Montri ekzemplojn</a></p>
				
				<?php else: ?>
					<div class="footer-info">
						Bonvolu atenti: ĉi morfanalizilo estas dua (serioza) provo krei ilon por rekoni la diversajn partojn de vortoj en Esperanto; 
						ĝi uzas la "literumilo" kodon kreita de Klivo Lendon, sed daŭre ne estas 100% preciza, do ĉiam kontrolu ĝiajn respondojn se ili ne konvinkas vin kaj, eble, sendu viajn 
						komentojn al la programisto, sed NE plendu: vivo estas tro mallonga por ĝin pasigi plendante...
					</div>
					
					<div class="esempio-titolo">Ekzemploj:</div>
					<?php 
						$test = array('ek', 'fidi', 'malplej', 'belegaj', 'ĉiulandanojn', 'malreskribita', 'ĉirkaŭdiri', 'preteriri', 'ĉimomente', 'kongresaliĝilo', 'krokodilo');
						foreach ($test as $t) {
							echo "<div class='risultato'>";
							echo "<div class='descrizione'>" . htmlspecialchars($t, ENT_QUOTES, 'UTF-8') . "</div>";
							$html = literumilo_check_word_html($t);
							echo $html ?? "?";
							echo "</div>";
						}
					?>
				<?php endif; ?>
			</div>
		</div>
	</body>
</html>