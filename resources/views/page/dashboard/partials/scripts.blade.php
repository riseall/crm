<script>
    window.DashboardPage = {
        access: @json($dashboardAccess ?? []),
        routes: {
            summary: "{{ route('dashboard.summary') }}",
            chartHari: "{{ route('dashboard.chart.hari') }}",
            chartBulan: "{{ route('dashboard.chart.bulan') }}",
            chartDivisi: "{{ route('dashboard.chart.divisi') }}",
            chartPilar: "{{ route('dashboard.chart.pilar') }}",
            chartCoverage: "{{ route('dashboard.chart.coverage') }}",
            drilldown: @json(($dashboardAccess['can_view_drilldown'] ?? true) ? route('dashboard.drilldown') : null),
            tableAnggota: "{{ route('dashboard.table.anggota') }}",
            productLookup: "{{ route('product.produk') }}",
        }
    };
</script>
<script
    src="{{ asset('js/dashboard/1_helper_rupiah.js') }}?v={{ filemtime(public_path('js/dashboard/1_helper_rupiah.js')) }}">
</script>
<script src="{{ asset('js/dashboard/2_card.js') }}?v={{ filemtime(public_path('js/dashboard/2_card.js')) }}"></script>
<script src="{{ asset('js/dashboard/3_chart.js') }}?v={{ filemtime(public_path('js/dashboard/3_chart.js')) }}"></script>
<script
    src="{{ asset('js/dashboard/4_drilldown.js') }}?v={{ filemtime(public_path('js/dashboard/4_drilldown.js')) }}">
</script>
<script
    src="{{ asset('js/dashboard/5_datatable.js') }}?v={{ filemtime(public_path('js/dashboard/5_datatable.js')) }}">
</script>
<script
    src="{{ asset('js/dashboard/6_dashboard.js') }}?v={{ filemtime(public_path('js/dashboard/6_dashboard.js')) }}">
</script>
