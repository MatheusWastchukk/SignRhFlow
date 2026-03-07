import { Component, OnDestroy, OnInit } from '@angular/core';
import { CommonModule } from '@angular/common';
import { Subject, interval, startWith, switchMap, takeUntil } from 'rxjs';
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
}
