import { Component, OnDestroy, OnInit } from '@angular/core';
import { CommonModule } from '@angular/common';
import { Subject, interval, startWith, switchMap, takeUntil } from 'rxjs';
import { environment } from '../../environments/environment';
import { ApiService } from '../services/api.service';
import { Contract } from '../models';

@Component({
  selector: 'app-dashboard-page',
  standalone: true,
  imports: [CommonModule],
  templateUrl: './dashboard-page.component.html',
})
export class DashboardPageComponent implements OnInit, OnDestroy {
  contracts: Contract[] = [];
  loading = true;
  error = '';
  showViewModal = false;
  deletingId: string | null = null;
  feedbackError = '';
  feedbackSuccess = '';
  viewContract: Contract | null = null;

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
          this.feedbackSuccess = '';
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
    return `${environment.apiBaseUrl}/contracts/${contractId}/pdf`;
  }

  signingUrl(token: string | null | undefined): string | null {
    if (!token) {
      return null;
    }

    return `/assinar/${token}`;
  }

  autentiqueSigningUrl(contract: Contract): string | null {
    const url = contract.autentique_signing_url;
    if (!url) {
      return null;
    }

    return url.startsWith('http') ? url : `https://${url}`;
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

  openViewModal(contract: Contract): void {
    this.viewContract = contract;
    this.showViewModal = true;
    this.feedbackSuccess = '';
    this.feedbackError = '';
  }

  closeViewModal(): void {
    this.showViewModal = false;
    this.viewContract = null;
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
    this.feedbackError = '';
    this.feedbackSuccess = '';

    this.apiService.deleteContract(contract.id).subscribe({
      next: () => {
        this.contracts = this.contracts.filter((item) => item.id !== contract.id);
        this.deletingId = null;
        this.feedbackSuccess = 'Contrato excluido com sucesso.';
      },
      error: (errorResponse) => {
        this.deletingId = null;
        this.feedbackError = errorResponse?.error?.message || 'Falha ao excluir contrato.';
      }
    });
  }
}
