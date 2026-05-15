<x-modal id="modalPm" title="Tambah PM" submitBtnId="btnSimpan">
    <input type="hidden" id="pmId">
    <div class="form-group">
        <label>Nama Produk <span class="text-danger">*</span></label>
        <select id="pt_ph" class="form-control select2-produk" style="width:100%"></select>
    </div>
    <div class="form-group">
        <label>Coverage <span class="text-danger">*</span></label>
        <select id="id_coverage_pm" class="form-control selectpicker" style="width:100%">
            <option value="">-- Pilih Coverage --</option>
            @foreach ($coverage as $cov)
                <option value="{{ $cov->id_coverage }}">
                    {{ $cov->id_coverage }} - {{ $cov->cov_nik }}
                    @if ($cov->user)
                        - {{ $cov->user->name }}
                    @endif
                </option>
            @endforeach
        </select>
    </div>
</x-modal>

@push('scripts')
    <script>
        $(document).ready(function() {
            var $modal = $('#modalPm');
            var $product = $('.select2-produk');
            var $coverage = $('#id_coverage_pm');

            if ($.fn.selectpicker) {
                $coverage.selectpicker('render');
            }

            $product.select2({
                dropdownParent: $modal,
                ajax: {
                    url: '{{ route('product.produk') }}',
                    dataType: 'json',
                    delay: 250,
                    data: function(params) {
                        return {
                            q: params.term
                        };
                    },
                    processResults: function(data) {
                        return {
                            results: data.results
                        };
                    },
                },
                placeholder: 'Cari kode atau nama produk...',
                minimumInputLength: 2,
            });

            $('#btnTambah').on('click', function(e) {
                e.preventDefault();
                resetProductModal();
                $('#modalPmLabel').text('Tambah PM');
                $modal.modal('show');
            });

            $(document).on('click', '.btn-edit', function() {
                var id = $(this).data('id');

                $.get('{{ route('product.show', ':id') }}'.replace(':id', id), function(data) {
                    resetProductModal();
                    $('#pmId').val(data.id);
                    $('#modalPmLabel').text('Edit PM');

                    var productText = data.mpart && data.mpart.pt_desc ?
                        data.pt_ph + ' - ' + data.mpart.pt_desc :
                        data.pt_ph;
                    var option = new Option(productText, data.pt_ph, true, true);

                    $product.append(option).trigger('change');
                    setCoverageValue(data.id_coverage);
                    $modal.modal('show');
                });
            });

            $('#btnSimpan').on('click', function() {
                var id = $('#pmId').val();
                var url = id ? '{{ route('product.update', ':id') }}'.replace(':id', id) : '{{ route('product.store') }}';
                var method = id ? 'PUT' : 'POST';

                $.ajax({
                    url: url,
                    type: method,
                    data: {
                        _token: '{{ csrf_token() }}',
                        pt_ph: $('#pt_ph').val(),
                        id_coverage: $coverage.val(),
                    },
                    success: function(res) {
                        $modal.modal('hide');
                        Swal.fire('Berhasil', res.message, 'success');
                        reloadProductTables();
                    },
                    error: function(xhr) {
                        var message = xhr.responseJSON && xhr.responseJSON.message ?
                            xhr.responseJSON.message :
                            'Data gagal disimpan.';
                        Swal.fire('Gagal', message, 'error');
                    }
                });
            });

            $(document).on('click', '.btn-delete', function() {
                var id = $(this).data('id');
                Swal.fire({
                    title: 'Hapus PM?',
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonText: 'Ya, hapus!',
                    cancelButtonText: 'Batal',
                }).then(function(result) {
                    if (result.isConfirmed) {
                        $.ajax({
                            url: '{{ route('product.destroy', ':id') }}'.replace(':id', id),
                            type: 'DELETE',
                            data: {
                                _token: '{{ csrf_token() }}'
                            },
                            success: function(res) {
                                Swal.fire('Berhasil', res.message, 'success');
                                reloadProductTables();
                            },
                            error: function(xhr) {
                                var message = xhr.responseJSON && xhr.responseJSON.message ?
                                    xhr.responseJSON.message :
                                    'Data gagal dihapus.';
                                Swal.fire('Gagal', message, 'error');
                            }
                        });
                    }
                });
            });

            function resetProductModal() {
                $('#pmId').val('');
                $product.val(null).trigger('change');
                $product.find('option').remove();
                setCoverageValue('');
            }

            function setCoverageValue(value) {
                var selectedValue = value || '';

                if ($.fn.selectpicker) {
                    $coverage.selectpicker('val', selectedValue);
                    $coverage.selectpicker('refresh');
                } else {
                    $coverage.val(selectedValue);
                    $coverage.trigger('change');
                }
            }

            function reloadProductTables() {
                if (window.tablePm) {
                    window.tablePm.ajax.reload(null, false);
                }

                if (window.tablePmDist) {
                    window.tablePmDist.ajax.reload(null, false);
                }
            }
        });
    </script>
@endpush
