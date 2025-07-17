@include('layout.header')

<body class="act-receiptnotes">
    <div class="main-content-area">
        <div class="container p-3 mx-auto">
            <div class="card shadow-sm w-100">
                <div class="card-header bg-primary d-flex justify-content-between align-items-center">
                    <h5 class="text-white mb-0">Receipt Notes</h5>
                    <a href="{{ route('receipt_notes.create') }}" class="btn btn-light">Create Receipt Note</a>
                </div>
                <div class="card-body p-3">
                    @if (session('success'))
                    <div class="alert alert-success">
                        {{ session('success') }}
                    </div>
                    @endif
                    @if ($errors->any())
                    <div class="alert alert-danger">
                        <ul>
                            @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                    @endif
                    <div class="table-responsive">
                        <table class="table table-hover align-middle">
                            <thead>
                                <tr>
                                    <th>Receipt Number</th>
                                    <th>Receipt Date</th>
                                    <th>Party</th>
                                    <th>Note</th>
                                    <th>Total with GST</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($receiptNotes as $note)
                                <tr>
                                    <td>{{ $note->receipt_number }}</td>
                                    <td>{{ $note->receipt_date }}</td>
                                    <td>{{ $note->party->name }}</td>
                                    <td>{{ $note->note ?? 'N/A' }}</td>
                                    <td>{{ number_format($note->items->sum('total_price'), 2) }}</td>
                                    <td>
                                        @if($note->is_converted)
                                            <span class="badge bg-success">Converted</span>
                                        @else
                                            <span class="badge bg-warning">Pending</span>
                                        @endif
                                    </td>
                                    <td class="d-flex gap-1">
                                        <a href="{{ route('receipt_notes.edit', $note->id) }}" class="btn btn-sm btn-warning">Edit</a>
                                        <a href="{{ route('receipt_notes.pdf', $note->id) }}" class="btn btn-sm btn-danger" target="_blank">
                                            <i class="fa fa-file-pdf"></i> PDF
                                        </a>
                                    </td>
                                </tr>

                                <!-- Modal for converting to Purchase Entry -->
                                <div class="modal fade" id="convertModal{{ $note->id }}" tabindex="-1" role="dialog"
                                    aria-labelledby="convertModalLabel{{ $note->id }}" aria-hidden="true">
                                    <div class="modal-dialog" role="document">
                                        <div class="modal-content">
                                            <div class="modal-header">
                                                <h5 class="modal-title" id="convertModalLabel{{ $note->id }}">Convert
                                                    Receipt Note to Purchase Entry</h5>
                                                <button type="button" class="close" data-dismiss="modal"
                                                    aria-label="Close">
                                                    <span aria-hidden="true">×</span>
                                                </button>
                                            </div>
                                            <div class="modal-body">
                                                <form action="{{ route('receipt_notes.convert', $note->id) }}"
                                                    method="POST">
                                                    @csrf
                                                    <div class="mb-3">
                                                        <label for="invoice_number" class="form-label">Invoice
                                                            Number</label>
                                                        <input type="text" name="invoice_number" class="form-control"
                                                            required>
                                                    </div>
                                                    <div class="mb-3">
                                                        <label for="invoice_date" class="form-label">Invoice
                                                            Date</label>
                                                        <input type="date" name="invoice_date" class="form-control"
                                                            required>
                                                    </div>
                                                    <button type="submit" class="btn btn-primary">Convert</button>
                                                </form>
                                            </div>
                                            <div class="modal-footer">
                                                <button type="button" class="btn btn-secondary"
                                                    data-dismiss="modal">Close</button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>

@include('layout.footer')