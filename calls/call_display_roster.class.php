<?php
/**
 * LEVI CPM
 * 
 *
 * @author Tom Barrett <tom@tombarrett.id.au>
 * @version $Id: call_display_roster.class.php,v 1.2 2013/03/19 09:47:51 tbar0970 Exp $
 * @package jethro-pmm
 */
class Call_Display_Roster extends Call
{
	/**
	 * Execute this call
	 *
	 * @return void
	 * @access public
	 */
	function run()
	{
		$roster_id = (int)array_get($_REQUEST, 'viewid');
		if (empty($roster_id)) return;
		$view = $GLOBALS['system']->getDBObject('roster_view', $roster_id);

		?>
		<html>
			<head>
				<style media="print">
					html body * {
						color: black;
						text-decoration: none;
					}
					.no-print {
						display: none !important;
					}
				</style>
				<style>
					* {
						font-family: sans-serif;
					}
					td, th {
						padding: 3px 1ex;
						font-size: 0.8em;
						vertical-align: top;
					}
					thead th {
						background-color: #555;
						color: white;
					}
					thead th * {
						color: white !important;
					}
					table {
						border-collapse: collapse;
					}
					/* Modern equivalent of table border="1" (so the JS-generated table keeps gridlines) */
					#body table.roster {
						border: 1px solid #999;
					}
					#body table.roster th,
					#body table.roster td {
						border: 1px solid #999;
					}
					.smallprint {
						margin-top: 1ex;
						font-size: 75%;
					}
					tbody .roster-date {
						text-align: right;
					}
					#body table.roster p {
						line-height: 1.0;
						margin: 1.5px 0px;
						padding: 1px;
						max-width: 20em;
					}
					#body table.roster p.title {
						font-style: italic;
						font-size: 105%;
					}
					#body table.roster p.bible {
						color: @jethroLinkColor;
						font-weight: 400;
					}
					#body table.roster p.bible strong {
						color: @jethroLinkColor;
						font-weight: 800;
					}
					#body table.roster p.notes {
						font-size: 80%;
						color: #666;
						font-style: italic;
					}
					.roster-transpose-link {
						position: absolute;
						top: 1em;
						right: 1em;
					}
					.roster-transpose-link a {
						display: inline-block;
						padding: 4px 10px;
						border: 1px solid rgba(0,0,0,0.25);
						border-radius: 5px;
						background: rgba(255,255,255,0.75);
						color: #222;
						text-decoration: none;
                        font-size: 80%;
						line-height: 1.2;
							box-shadow: 0 2px 6px rgba(0,0,0,0.25);
							transition: box-shadow 120ms ease, transform 120ms ease, background 120ms ease;
						}
						.roster-transpose-link a:hover {
							box-shadow: 0 4px 12px rgba(0,0,0,0.30);
							transform: translateY(-1px);
							background: rgba(255,255,255,0.90);
						}
						.roster-transpose-link a:active {
							box-shadow: 0 2px 4px rgba(0,0,0,0.22);
							transform: translateY(0);
						}
					</style>

		<script type="text/javascript">
		function transposeRosterTable() {
			var table = document.querySelector('#body table.roster');
			if (!table) return;

			// --- Simplify HTML first, removing the service name row
			if (table.tHead && table.tHead.rows && table.tHead.rows.length === 2) {
				// Keep the last header row; remove the first two
				var thead = table.tHead;
				thead.removeChild(thead.rows[0]);

				// Insert <td>Date</td> at the start of the remaining header row
				var remainingTr = thead.rows[0];
				if (remainingTr) {
					var dateCell = document.createElement('td');
					dateCell.innerHTML = 'Date';
					remainingTr.insertBefore(dateCell, remainingTr.firstChild);
				}
			}

			// --- Collect rows in DOM order: THEAD then TBODY ---
			var rows = [];
			if (table.tHead && table.tHead.rows) {
				for (var h = 0; h < table.tHead.rows.length; h++) rows.push(table.tHead.rows[h]);
			}
			if (table.tBodies && table.tBodies.length) {
				for (var b = 0; b < table.tBodies.length; b++) {
					for (var r = 0; r < table.tBodies[b].rows.length; r++) rows.push(table.tBodies[b].rows[r]);
				}
			}
			if (!rows.length) return;

			// --- Build a rectangular matrix of cell HTML ---
			var matrix = [];
			var maxCols = 0;
			for (var rr = 0; rr < rows.length; rr++) {
				var cells = rows[rr].children;
				var rowArr = [];
				for (var cc = 0; cc < cells.length; cc++) rowArr.push(cells[cc].innerHTML);
				if (rowArr.length > maxCols) maxCols = rowArr.length;
				matrix.push(rowArr);
			}
			for (var rr2 = 0; rr2 < matrix.length; rr2++) {
				while (matrix[rr2].length < maxCols) matrix[rr2].push('');
			}

			// After normalisation above, we assume exactly 1 header row after transpose.
			var headerRowCount = 1;

			// --- Build transposed table with THEAD + TBODY ---
			var newTable = document.createElement('table');
			newTable.className = table.className;

			var newThead = document.createElement('thead');
			var newTbody = document.createElement('tbody');
			newTable.appendChild(newThead);
			newTable.appendChild(newTbody);

			for (var newRow = 0; newRow < maxCols; newRow++) {
				var tr = document.createElement('tr');

				for (var newCol = 0; newCol < matrix.length; newCol++) {
					var isHeaderRow = (newRow < headerRowCount);
					var isRowHeaderCell = (!isHeaderRow && (newCol === 0));
					var tag = (isHeaderRow || isRowHeaderCell) ? 'th' : 'td';

					var cell = document.createElement(tag);
					cell.innerHTML = matrix[newCol][newRow];
					tr.appendChild(cell);
				}

				if (newRow < headerRowCount) {
					newThead.appendChild(tr);
				} else {
					newTbody.appendChild(tr);
				}
			}

			table.parentNode.replaceChild(newTable, table);
		}
		</script>
			</head>
			<body id="body">
				<h1>Roster: <?php $view->printFieldValue('name'); ?></h1>
				<div class="roster-transpose-link no-print">
					<a href="#" onclick="transposeRosterTable(); return false;">⤢ Transpose</a>
				</div>
<?php

		$start_date = substr(array_get($_REQUEST, 'start_date', ''), 0, 10);
		$end_date = substr(array_get($_REQUEST, 'end_date', ''), 0, 10);
		$view->printView($start_date, $end_date, FALSE, TRUE, TRUE);
?>
			</body>
		</html>
<?php
	}
}