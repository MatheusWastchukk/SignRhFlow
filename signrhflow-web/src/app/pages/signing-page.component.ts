import { Component, OnInit } from '@angular/core';
import { CommonModule } from '@angular/common';
import { ActivatedRoute } from '@angular/router';
import { ApiService } from '../services/api.service';
import { SigningContextResponse } from '../models';

@Component({
  selector: 'app-signing-page',
  standalone: true,
  imports: [CommonModule],
  templateUrl: './signing-page.component.html',
})
export class SigningPageComponent implements OnInit {
  loading = true;
  error = '';
  context: SigningContextResponse | null = null;

  constructor(
    private readonly route: ActivatedRoute,
    private readonly apiService: ApiService
  ) {}

  ngOnInit(): void {
    const token = this.route.snapshot.paramMap.get('token');

    if (!token) {
      this.loading = false;
      this.error = 'Link de assinatura invalido.';
      return;
    }

    this.apiService.getSigningContext(token).subscribe({
      next: (context) => {
        this.context = context;
        this.loading = false;
      },
      error: (errorResponse) => {
        this.error = errorResponse?.error?.message || 'Nao foi possivel carregar o contexto de assinatura.';
        this.loading = false;
      }
    });
  }
}
