<script>
    window.DashboardPage = {
        routes: {
            summary: "{{ route('dashboard.summary') }}",
            chartHari: "{{ route('dashboard.chart.hari') }}",
            chartBulan: "{{ route('dashboard.chart.bulan') }}",
            chartDivisi: "{{ route('dashboard.chart.divisi') }}",
            chartPilar: "{{ route('dashboard.chart.pilar') }}",
            chartCoverage: "{{ route('dashboard.chart.coverage') }}",
            drilldown: "{{ route('dashboard.drilldown') }}",
            tableAnggota: "{{ route('dashboard.table.anggota') }}",
        }
    };
</script>
<script src="{{ asset('js/dashboard/1_helper_rupiah.js') }}"></script>
<script src="{{ asset('js/dashboard/2_card.js') }}"></script>
<script src="{{ asset('js/dashboard/3_chart.js') }}"></script>
<script src="{{ asset('js/dashboard/4_drilldown.js') }}"></script>
<script src="{{ asset('js/dashboard/5_datatable.js') }}"></script>
<script src="{{ asset('js/dashboard/6_dashboard.js') }}"></script>
