<script>
    window.DashboardPage = {
        access: @json($dashboardAccess ?? []),
        routes: {
            consolidatedData: "{{ route('dashboard.consolidated') }}",
            chartBulan: "{{ route('dashboard.chart.bulan') }}",
            chartRace: "{{ route('dashboard.chart.race') }}",
            drilldown: @json($dashboardAccess['can_view_drilldown'] ?? true ? route('dashboard.drilldown') : null),
            tableAnggota: "{{ route('dashboard.table.anggota') }}",
            tableAchievement: "{{ route('dashboard.table.achievement') }}",
            productLookup: "{{ route('product.produk') }}",
            detailAnggota: "{{ route('dashboard.anggota.detail', ':nik') }}",
            visitsDetail: "{{ route('visits.index') }}?nik=:nik",
            outletHistory: "{{ route('outlet.history') }}",
        }
    };
</script>
<script
    src="{{ asset('js/dashboard/1_helper_rupiah.js') }}?v={{ filemtime(public_path('js/dashboard/1_helper_rupiah.js')) }}">
</script>
<script src="{{ asset('js/dashboard/2_card.js') }}?v={{ filemtime(public_path('js/dashboard/2_card.js')) }}"></script>
<script src="{{ asset('js/dashboard/3_chart.js') }}?v={{ filemtime(public_path('js/dashboard/3_chart.js')) }}">
</script>
<script
    src="{{ asset('js/dashboard/4_drilldown.js') }}?v={{ filemtime(public_path('js/dashboard/4_drilldown.js')) }}">
</script>
<script
    src="{{ asset('js/dashboard/5_datatable.js') }}?v={{ filemtime(public_path('js/dashboard/5_datatable.js')) }}">
</script>
<script
    src="{{ asset('js/dashboard/6_dashboard.js') }}?v={{ filemtime(public_path('js/dashboard/6_dashboard.js')) }}">
</script>
