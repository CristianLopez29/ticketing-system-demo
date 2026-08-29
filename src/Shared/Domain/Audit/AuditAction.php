<?php

declare(strict_types=1);

namespace Src\Shared\Domain\Audit;

enum AuditAction: string
{
    case LoginSucceeded = 'auth.login_succeeded';
    case LoginFailed = 'auth.login_failed';
    case Logout = 'auth.logout';
    case TokenRefreshed = 'auth.token_refreshed';
    case TokensRevoked = 'auth.tokens_revoked';
    case ReportDownloaded = 'report.downloaded';
    case TicketIssued = 'ticket.issued';
    case ReservationPaid = 'reservation.paid';
    case ReservationCancelled = 'reservation.cancelled';
    case SeasonTicketPaid = 'season_ticket.paid';
    case PaymentCompensated = 'payment.compensated';
    case RefundPending = 'refund.pending';
    case ReservationExpired = 'reservation.expired';
}
