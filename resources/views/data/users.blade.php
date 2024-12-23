@extends('layouts.backend')
@section('content')

<div class="row">
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Tabel Data User</h3>
            <div class="col-auto ms-auto d-print-none">

                <div class="btn-list">
                    <button type="button" class="btn btn-info d-none d-sm-inline-block" data-bs-toggle="modal" data-bs-target="#CreateModal">
                        <i data-feather="plus"></i>Tambah User
                    </button>
                </div>
            </div>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table id="example" class="table table-vcenter card-table table-striped">
                    <thead>
                        <tr>
                            <th>No</th>
                            <th>Nama</th>
                            <th>Email</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($user as $row)
                        <tr>
                            <td>{{ $loop->iteration }}</td>
                            <td>{{ $row->name }}</td>
                            <td>{{ $row->email }}</td>
                            <td>
                                <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#editModal{{ $row->id }}">
                                    Edit
                                </button>
                                <!-- <button class="btn btn-danger btn-sm btn-hapus" id="{{ $row->id }}">Hapus</button> -->
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

{{-- Modal Edit --}}

@foreach ($user as $item)
<div class="modal fade" id="editModal{{ $item->id }}" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="exampleModalLabel{{ $item->id }}">Edit Item: {{ $item->name }}</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <!-- Form starts here -->
                <form action="{{ route('users.update', $item->id) }}" method="POST">
                    @csrf
                    @method('PUT')
                    <div class="row">
                        <div class="col mb-3">
                            <label for="name" class="form-label">Name</label>
                            <input type="text" id="name" name="name" class="form-control" value="{{ $item->name }}" placeholder="Enter Name" />
                        </div>


                    </div>
                    <div class="row">

                        <div class="col mb-3">
                            <label for="email" class="form-label">Email sss</label>
                            <input type="email" id="email" name="email" class="form-control" value="{{ $item->email }}" placeholder="email@example.com" />
                        </div>
                    </div>
                    <div class="row">
                        <div class="col mb-3">
                            <label for="password" class="form-label">Password</label>
                            <input type="password" id="password" name="password" class="form-control" value="" placeholder="Password" />
                        </div>

                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">
                            Close
                        </button>
                        <button type="submit" class="btn btn-primary">Save changes</button>
                    </div>
                </form>

                <!-- Form ends here -->
            </div>

        </div>
    </div>
</div>
@endforeach


<div class="modal fade" id="CreateModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="exampleCreateModal">Tambah Akun</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <!-- Form starts here -->
                <form action="{{ route('users.store') }}" method="POST">
                    @csrf
                    <div class="row">
                        <div class="col mb-3">
                            <label for="name" class="form-label">Name</label>
                            <input type="text" id="name" name="name" class="form-control" value="" placeholder="Enter Name" />
                        </div>


                    </div>
                    <div class="row">

                        <div class="col mb-3">
                            <label for="email" class="form-label">Email</label>
                            <input type="email" id="email" name="email" class="form-control" value="" placeholder="email@example.com" />
                        </div>
                    </div>
                    <div class="row">
                        <div class="col mb-3">
                            <label for="password" class="form-label">Password</label>
                            <input type="password" id="password" name="password" class="form-control" value="" placeholder="Password" />
                        </div>

                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">
                            Close
                        </button>
                        <button type="submit" class="btn btn-primary">Save changes</button>
                    </div>
                </form>

                <!-- Form ends here -->
            </div>

        </div>
    </div>
</div>


@endsection