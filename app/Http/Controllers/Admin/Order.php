<?php
namespace App\Http\Controllers\Admin; use App\Library\FundHelper; use App\Library\Helper; use Illuminate\Database\Eloquent\Relations\Relation; use Illuminate\Http\Request; use App\Http\Controllers\Controller; use App\Library\Response; use Illuminate\Support\Facades\DB; use Illuminate\Support\Facades\Log; class Order extends Controller { function stat(Request $sp0fc69c) { $sp4cd8b1 = (int) $sp0fc69c->input('day', 7); $sp6edbd3 = $sp0fc69c->post('profit') === 'true'; $sp3f78ce = \App\Order::where(function ($sp3f78ce) { $sp3f78ce->where('status', \App\Order::STATUS_PAID)->orWhere('status', \App\Order::STATUS_SUCCESS); })->where('paid_at', '>=', Helper::getMysqlDate(-$sp4cd8b1 + 1))->groupBy('date')->orderBy('date', 'DESC'); if ($sp6edbd3) { $sp3f78ce->selectRaw('DATE(`paid_at`) as "date",COUNT(*) as "count",SUM(`fee`-`system_fee`) as "sum"'); } else { $sp3f78ce->selectRaw('DATE(`paid_at`) as "date",COUNT(*) as "count",SUM(`paid`) as "sum"'); } $sp36eb9c = $sp3f78ce->get()->toArray(); $spc8aebe = array(); foreach ($sp36eb9c as $spf427c6) { $spc8aebe[$spf427c6['date']] = array((int) $spf427c6['count'], (int) $spf427c6['sum']); } return Response::success($spc8aebe); } public function delete(Request $sp0fc69c) { $spf179c6 = $sp0fc69c->post('ids', ''); $sp16160a = (int) $sp0fc69c->post('income'); $spf86c72 = (int) $sp0fc69c->post('balance'); if (strlen($spf179c6) < 1) { return Response::forbidden(); } \App\Order::whereIn('id', explode(',', $spf179c6))->chunk(100, function ($sp27d805) use($sp16160a, $spf86c72) { foreach ($sp27d805 as $sp7fd294) { $sp7fd294->cards()->detach(); try { if ($sp16160a) { $sp7fd294->fundRecord()->delete(); } if ($spf86c72) { $sp3353ce = \App\User::lockForUpdate()->firstOrFail(); $sp3353ce->m_all -= $sp7fd294->income; $sp3353ce->saveOrFail(); } $sp7fd294->delete(); } catch (\Exception $sp2a4a9a) { } } }); return Response::success(); } function freeze(Request $sp0fc69c) { $spf179c6 = $sp0fc69c->post('ids', ''); if (strlen($spf179c6) < 1) { return Response::forbidden(); } $spd91176 = $sp0fc69c->post('reason'); $sp27d805 = \App\Order::whereIn('id', explode(',', $spf179c6))->where('status', \App\Order::STATUS_SUCCESS)->get(); $sp1f1cf0 = 0; $spa5748e = 0; foreach ($sp27d805 as $sp7fd294) { if (FundHelper::orderFreeze($sp7fd294, $spd91176)) { $spa5748e++; } $sp1f1cf0++; } return Response::success(array($sp1f1cf0, $spa5748e)); } function unfreeze(Request $sp0fc69c) { $spf179c6 = $sp0fc69c->post('ids', ''); if (strlen($spf179c6) < 1) { return Response::forbidden(); } $sp27d805 = \App\Order::whereIn('id', explode(',', $spf179c6))->where('status', \App\Order::STATUS_FROZEN)->get(); $sp1f1cf0 = 0; $spa5748e = 0; $sp7bcfe8 = \App\Order::STATUS_FROZEN; foreach ($sp27d805 as $sp7fd294) { if (FundHelper::orderUnfreeze($sp7fd294, '后台操作', null, $sp7bcfe8)) { $spa5748e++; } $sp1f1cf0++; } return Response::success(array($sp1f1cf0, $spa5748e, $sp7bcfe8)); } function set_paid(Request $sp0fc69c) { $spfc3b4d = (int) $sp0fc69c->post('id', ''); if ($spfc3b4d < 1) { return Response::forbidden(); } $sp2296f4 = $sp0fc69c->post('trade_no', ''); if (strlen($sp2296f4) < 1) { return Response::forbidden('请输入支付系统内单号'); } $sp7fd294 = \App\Order::findOrFail($spfc3b4d); if ($sp7fd294->status !== \App\Order::STATUS_UNPAY) { return Response::forbidden('只能操作未支付订单'); } $spe770c0 = 'Admin.SetPaid'; $sp044e8f = $sp7fd294->order_no; $spbd56f5 = $sp7fd294->paid; try { Log::debug($spe770c0 . " shipOrder start, order_no: {$sp044e8f}, amount: {$spbd56f5}, trade_no: {$sp2296f4}"); (new \App\Http\Controllers\Shop\Pay())->shipOrder($sp0fc69c, $sp044e8f, $spbd56f5, $sp2296f4, FALSE); Log::debug($spe770c0 . ' shipOrder end, order_no: ' . $sp044e8f); $spa5748e = true; $spbfa8f4 = '发货成功'; } catch (\Exception $sp2a4a9a) { $spa5748e = false; $spbfa8f4 = $sp2a4a9a->getMessage(); Log::error($spe770c0 . ' shipOrder Exception: ' . $sp2a4a9a->getMessage()); } $sp7fd294 = \App\Order::with(array('card_orders.card' => function (Relation $sp3f78ce) { $sp3f78ce->select(array('id', 'card')); }))->findOrFail($spfc3b4d); if ($sp7fd294->status === \App\Order::STATUS_PAID) { $spa5748e = false; $spbfa8f4 = '已标记为付款成功, 但是买家库存不足, 发货失败, 请稍后尝试手动发货'; } return Response::success(array('code' => $spa5748e ? 0 : -1, 'msg' => $spbfa8f4, 'order' => $sp7fd294)); } }