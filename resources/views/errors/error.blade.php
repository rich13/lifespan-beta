@extends('errors.layout')

@section('error_code', $exception->getStatusCode() ?? 'Error')
@section('error_title', $exception->getMessage() ?: 'An error occurred')
@section('error_message', 'Something to do with computers.')
@section('error_color', 'danger')
@section('error_icon', 'bi-exclamation-circle') 