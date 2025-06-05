document.addEventListener('DOMContentLoaded', () => {
	const chartCanvas = document.getElementById('resultChart');
	if (!chartCanvas || !chartCanvas.dataset.points) return;

	let rawData, logLine,powerLine,lowessLine;

	try {
		rawData = JSON.parse(chartCanvas.dataset.points);
		logLine = JSON.parse(chartCanvas.dataset.lineLog || '[]');
		powerLine = chartCanvas.dataset.linePower ? JSON.parse(chartCanvas.dataset.linePower) : [];
		lowessLine = chartCanvas.dataset.lineLowess ? JSON.parse(chartCanvas.dataset.lineLowess) : []; 
	} catch (e) {
		console.error('Erreur de parsing des données du graphique :', e);
		return;
	}

	if (!rawData.length) return;

	const slopeData = [...rawData].sort((a, b) => a.x - b.x);
	const slopeStart = slopeData[0];
	const slopeEnd = slopeData[slopeData.length - 1];

	const slopeY = x => {
		const n = rawData.length;
		const sumX = rawData.reduce((acc, v) => acc + v.x, 0);
		const sumY = rawData.reduce((acc, v) => acc + v.y, 0);
		const sumXY = rawData.reduce((acc, v) => acc + v.x * v.y, 0);
		const sumX2 = rawData.reduce((acc, v) => acc + v.x * v.x, 0);
		const slope = (n * sumXY - sumX * sumY) / (n * sumX2 - sumX * sumX);
		const intercept = (sumY - slope * sumX) / n;
		return slope * x + intercept;
	};

	const slopeLine = [
		{ x: slopeStart.x, y: slopeY(slopeStart.x) },
		{ x: slopeEnd.x, y: slopeY(slopeEnd.x) }
	];

	const ctx = chartCanvas.getContext('2d');
	new Chart(ctx, {
		type: 'scatter',
		data: {
			datasets: [
				{
					label: 'Données sélectionnées',
					data: rawData,
					backgroundColor: 'rgba(75, 192, 192, 0.8)',
					showLine: false
				},
				{
					label: 'Pente linéaire',
					data: slopeLine,
					type: 'line',
					borderColor: 'rgba(255, 99, 132, 1)',
					borderWidth: 2,
					fill: false,
					pointRadius: 0
				},
				{
					label: 'Régression logarithmique',
					data: logLine,
					type: 'line',
					borderColor: 'green',
					borderDash: [5, 5],
					borderWidth: 2,
					pointRadius: 0,
					fill: false
				},
				{
					label: 'Régression puissance',
					data: powerLine,
					type: 'line',
					borderColor: 'orange',
					borderDash: [2, 3],
					borderWidth: 2,
					fill: false,
					pointRadius: 0
				},
				{
					label: 'Régression LOWESS',
					data: lowessLine,
					type: 'line',
					borderColor: 'blue',
					borderDash: [1, 1],
					borderWidth: 2,
					fill: false,
					pointRadius: 0
				}
			]
		},
		options: {
			responsive: true,
			scales: {
				x: {
					title: {
						display: true,
						text: 'Surface (m²)'
					}
				},
				y: {
					title: {
						display: true,
						text: 'Prix (€)'
					}
				}
			}
		}
	});
});
