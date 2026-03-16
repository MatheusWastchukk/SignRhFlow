import { Component, OnDestroy, OnInit } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { Subject, interval, startWith, switchMap, takeUntil } from 'rxjs';
import { ApiService } from '../services/api.service';
import { Contract } from '../models';

@Component({
  selector: 'app-dashboard-page',
  standalone: true,
  imports: [CommonModule, FormsModule],
  templateUrl: './dashboard-page.component.html',
})
export class DashboardPageComponent implements OnInit, OnDestroy {
  readonly countryOptions = [
    { code: 'BR', label: '+55 BR', dialCode: '+55' },
    { code: 'US', label: '+1 US', dialCode: '+1' },
    { code: 'PT', label: '+351 PT', dialCode: '+351' },
  ] as const;

  contracts: Contract[] = [];
  loading = true;
  error = '';
  showEditModal = false;
  showViewModal = false;
  editing = false;
  deletingId: string | null = null;
  editError = '';
  editSuccess = '';
  viewContract: Contract | null = null;
  editModel: {
    id: string;
    status: Contract['status'];
    delivery_method: Contract['delivery_method'];
    employee_name: string;
    employee_email: string;
    employee_phone_country: 'BR' | 'US' | 'PT';
    employee_phone: string;
    employee_cpf: string;
  } = {
    id: '',
    status: 'DRAFT',
    delivery_method: 'EMAIL',
    employee_name: '',
    employee_email: '',
    employee_phone_country: 'BR',
    employee_phone: '',
    employee_cpf: '',
  };
  private readonly destroy$ = new Subject<void>();

  constructor(private readonly apiService: ApiService) {}

  ngOnInit(): void {
    interval(3000)
      .pipe(
        startWith(0),
        switchMap(() => this.apiService.listContracts()),
        takeUntil(this.destroy$)
      )
      .subscribe({
        next: (response) => {
          this.contracts = response.data ?? [];
          this.loading = false;
          this.error = '';
          this.editSuccess = '';
        },
        error: () => {
          this.loading = false;
          this.error = 'Falha ao carregar contratos.';
        }
      });
  }

  ngOnDestroy(): void {
    this.destroy$.next();
    this.destroy$.complete();
  }

  pdfUrl(contractId: string): string {
    return `http://localhost:8000/api/contracts/${contractId}/pdf`;
  }

  signingUrl(token: string | null | undefined): string | null {
    if (!token) {
      return null;
    }

    return `/assinar/${token}`;
  }

  formatDateTime(value: string | null | undefined): string {
    if (!value) {
      return '-';
    }

    const date = new Date(value);
    if (Number.isNaN(date.getTime())) {
      return '-';
    }

    return new Intl.DateTimeFormat('pt-BR', {
      dateStyle: 'short',
      timeStyle: 'short',
    }).format(date);
  }

  get totalContracts(): number {
    return this.contracts.length;
  }

  get pendingContracts(): number {
    return this.contracts.filter((c) => c.status === 'PENDING').length;
  }

  get signedContracts(): number {
    return this.contracts.filter((c) => c.status === 'SIGNED').length;
  }

  statusClasses(status: Contract['status']): string {
    switch (status) {
      case 'SIGNED':
        return 'bg-emerald-100 text-emerald-700';
      case 'REJECTED':
        return 'bg-rose-100 text-rose-700';
      case 'PENDING':
        return 'bg-amber-100 text-amber-700';
      default:
        return 'bg-slate-100 text-slate-700';
    }
  }

  documentAutentiqueLabel(contract: Contract): string {
    if (contract.autentique_document_id) {
      return contract.autentique_document_id;
    }

    if (contract.status === 'PENDING' || contract.status === 'DRAFT') {
      return 'Aguardando processamento';
    }

    return '-';
  }

  openEditModal(contract: Contract): void {
    this.editError = '';
    this.editSuccess = '';
    const detectedCountry = this.detectCountryFromPhone(contract.employee?.phone ?? '');
    this.editModel = {
      id: contract.id,
      status: contract.status,
      delivery_method: contract.delivery_method,
      employee_name: contract.employee?.name ?? '',
      employee_email: contract.employee?.email ?? '',
      employee_phone_country: detectedCountry,
      employee_phone: this.maskPhoneForCountry(this.stripCountryPrefix(contract.employee?.phone ?? '', detectedCountry), detectedCountry),
      employee_cpf: contract.employee?.cpf ?? '',
    };
    this.showEditModal = true;
  }

  openViewModal(contract: Contract): void {
    this.viewContract = contract;
    this.showViewModal = true;
    this.editSuccess = '';
    this.editError = '';
  }

  closeViewModal(): void {
    this.showViewModal = false;
    this.viewContract = null;
  }

  closeEditModal(): void {
    if (this.editing) {
      return;
    }

    this.showEditModal = false;
    this.editError = '';
  }

  saveContractEdit(): void {
    if (!this.editModel.id || this.editing) {
      return;
    }

    this.editing = true;
    this.editError = '';
    this.editSuccess = '';

    this.apiService.updateContract(this.editModel.id, {
      status: this.editModel.status,
      delivery_method: this.editModel.delivery_method,
      employee: {
        name: this.editModel.employee_name.trim(),
        email: this.editModel.employee_email.trim().toLowerCase(),
        phone: this.toE164(this.editModel.employee_phone_country, this.editModel.employee_phone),
        cpf: this.editModel.employee_cpf.trim(),
      },
    }).subscribe({
      next: (updated) => {
        this.contracts = this.contracts.map((item) => (item.id === updated.id ? updated : item));
        this.editing = false;
        this.showEditModal = false;
        this.editSuccess = 'Contrato atualizado com sucesso.';
      },
      error: (errorResponse) => {
        this.editing = false;
        const errors = errorResponse?.error?.errors;
        if (errors && typeof errors === 'object') {
          const firstField = Object.keys(errors)[0];
          const firstMessage = Array.isArray(errors[firstField]) ? errors[firstField][0] : '';
          this.editError = firstMessage || 'Falha ao atualizar contrato.';
          return;
        }

        this.editError = errorResponse?.error?.message || 'Falha ao atualizar contrato.';
      }
    });
  }

  deleteContract(contract: Contract): void {
    if (this.deletingId || !contract?.id) {
      return;
    }

    const shouldDelete = window.confirm(`Deseja realmente excluir o contrato ${contract.id}?`);
    if (!shouldDelete) {
      return;
    }

    this.deletingId = contract.id;
    this.editError = '';
    this.editSuccess = '';

    this.apiService.deleteContract(contract.id).subscribe({
      next: () => {
        this.contracts = this.contracts.filter((item) => item.id !== contract.id);
        this.deletingId = null;
        this.editSuccess = 'Contrato excluido com sucesso.';
      },
      error: (errorResponse) => {
        this.deletingId = null;
        this.editError = errorResponse?.error?.message || 'Falha ao excluir contrato.';
      }
    });
  }

  onEditPhoneCountryChange(): void {
    const digits = this.normalizePhoneDigits(this.editModel.employee_phone);
    this.editModel.employee_phone = this.maskPhoneForCountry(digits, this.editModel.employee_phone_country);
  }

  onEditPhoneInput(event: Event): void {
    const input = event.target as HTMLInputElement;
    input.value = this.maskPhoneForCountry(
      this.normalizePhoneDigits(input.value),
      this.editModel.employee_phone_country
    );
    this.editModel.employee_phone = input.value;
  }

  private detectCountryFromPhone(phone: string): 'BR' | 'US' | 'PT' {
    const normalized = (phone ?? '').trim();
    if (normalized.startsWith('+351')) return 'PT';
    if (normalized.startsWith('+1')) return 'US';
    return 'BR';
  }

  private stripCountryPrefix(phone: string, country: 'BR' | 'US' | 'PT'): string {
    const digits = this.normalizePhoneDigits(phone);
    const prefix = country === 'BR' ? '55' : country === 'US' ? '1' : '351';
    if (digits.startsWith(prefix)) {
      return digits.slice(prefix.length);
    }

    return digits;
  }

  private normalizePhoneDigits(value: string): string {
    return (value ?? '').replace(/\D/g, '');
  }

  private maskPhoneForCountry(digits: string, country: 'BR' | 'US' | 'PT'): string {
    const list = digits.slice(0, country === 'BR' ? 11 : country === 'US' ? 10 : 9);

    if (country === 'BR') {
      if (list.length <= 2) return list;
      if (list.length <= 7) return `(${list.slice(0, 2)}) ${list.slice(2)}`;
      return `(${list.slice(0, 2)}) ${list.slice(2, 7)}-${list.slice(7, 11)}`;
    }

    if (country === 'US') {
      if (list.length <= 3) return list;
      if (list.length <= 6) return `(${list.slice(0, 3)}) ${list.slice(3)}`;
      return `(${list.slice(0, 3)}) ${list.slice(3, 6)}-${list.slice(6, 10)}`;
    }

    if (list.length <= 3) return list;
    if (list.length <= 6) return `${list.slice(0, 3)} ${list.slice(3)}`;
    return `${list.slice(0, 3)} ${list.slice(3, 6)} ${list.slice(6, 9)}`;
  }

  private toE164(country: 'BR' | 'US' | 'PT', maskedPhone: string): string {
    const digits = this.normalizePhoneDigits(maskedPhone);
    const dialCode = this.countryOptions.find((item) => item.code === country)?.dialCode ?? '+55';
    return `${dialCode}${digits}`;
  }
}
