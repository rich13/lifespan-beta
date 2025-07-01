@extends('errors.layout')

@section('error_code', '422')
@section('error_title', 'Unprocessable Entity')
@section('error_message', 'The request was well-formed but could not be processed due to semantic errors.')
@section('error_color', 'warning')
@section('error_icon', 'bi-exclamation-circle') 