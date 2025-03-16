@props(['span'])

<div class="card mb-4">
    <div class="card-body">
        <h2 class="card-title h5 mb-3">Dates</h2>

        <!-- Start Date -->
        <div class="mb-3">
            <label class="form-label">Start Date</label>
            <div class="row g-2">
                <div class="col-4">
                    <input type="text" class="form-control @error('start_day') is-invalid @enderror" 
                           name="start_day" placeholder="DD"
                           value="{{ old('start_day', $span->start_day) }}">
                    @error('start_day')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-4">
                    <input type="text" class="form-control @error('start_month') is-invalid @enderror" 
                           name="start_month" placeholder="MM"
                           value="{{ old('start_month', $span->start_month) }}">
                    @error('start_month')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-4">
                    <input type="text" class="form-control @error('start_year') is-invalid @enderror" 
                           name="start_year" placeholder="YYYY"
                           value="{{ old('start_year', $span->start_year) }}">
                    @error('start_year')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
            </div>
        </div>

        <!-- End Date -->
        <div class="mb-3">
            <label class="form-label">End Date</label>
            <div class="row g-2">
                <div class="col-4">
                    <input type="text" class="form-control @error('end_day') is-invalid @enderror" 
                           name="end_day" placeholder="DD"
                           value="{{ old('end_day', $span->end_day) }}">
                    @error('end_day')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-4">
                    <input type="text" class="form-control @error('end_month') is-invalid @enderror" 
                           name="end_month" placeholder="MM"
                           value="{{ old('end_month', $span->end_month) }}">
                    @error('end_month')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-4">
                    <input type="text" class="form-control @error('end_year') is-invalid @enderror" 
                           name="end_year" placeholder="YYYY"
                           value="{{ old('end_year', $span->end_year) }}">
                    @error('end_year')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
            </div>
        </div>
    </div>
</div> 