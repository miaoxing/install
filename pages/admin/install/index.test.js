import Index from './index';
import $, {Ret} from 'miaoxing';
import {fireEvent, render, waitFor} from '@testing-library/react';
import {bootstrap, resetUrl, setUrl} from '@mxjs/test';
import {app} from '@mxjs/app';

bootstrap();

describe('install', () => {
  beforeEach(() => {
    setUrl('admin/install');
    app.page = {
      collection: 'admin/install',
      index: true,
    };
  });

  afterEach(() => {
    resetUrl();
    app.page = {};
  });

  test('install', async () => {
    $.http = jest.fn()
      .mockResolvedValueOnce({
        ret: Ret.suc({
          data: {
            installRet: Ret.suc({
              data: [
                Ret.suc('check suc 1'),
                Ret.suc('check suc 2'),
              ],
            }),
            license: 'xxx',
          },
        }),
      })
      // 测试 rewrite
      .mockResolvedValueOnce({
        ret: Ret.suc(),
      })
      .mockResolvedValueOnce({
        ret: Ret.suc(),
      });

    const result = render(<Index/>);

    await result.findByText('check suc 1');

    fireEvent.click(result.getByText('进入安装'));
    fireEvent.change(result.getByLabelText('数据库密码'), {target: {value: 'password'}});
    fireEvent.change(result.getByLabelText('管理员密码'), {target: {value: '123456'}});
    fireEvent.click(result.getByLabelText('安装演示数据'));
    fireEvent.click(result.getByText('我已阅读并同意'));
    fireEvent.click(result.getByText('安 装'));

    await waitFor(() => {
      expect($.http).toBeCalledTimes(3);
    });

    expect($.http).toMatchSnapshot();
  });

  test('check reqs', async () => {
    $.http = jest.fn()
      .mockResolvedValueOnce({
        ret: Ret.suc({
          data: {
            installRet: Ret.err({
              data: [
                Ret.suc('check suc 1'),
                Ret.err('check fail 2'),
              ],
            }),
            license: 'xxx',
          },
        }),
      })
      // 测试 rewrite
      .mockResolvedValueOnce({
        ret: Ret.suc(),
      })
      .mockResolvedValueOnce({
        ret: Ret.suc({
          data: {
            installRet: Ret.err({
              data: [
                Ret.err('check fail 1'),
                Ret.suc('check suc 2'),
              ],
            }),
            license: 'xxx',
          },
        }),
      })
      .mockResolvedValueOnce({
        ret: Ret.suc({
          data: {
            installRet: Ret.suc({
              data: [
                Ret.suc('check suc 1'),
                Ret.suc('check suc 2'),
              ],
            }),
            license: 'xxx',
          },
        }),
      });

    const result = render(<Index/>);

    await result.findByText('check suc 1');

    fireEvent.click(result.getByText('重新检查'));

    await result.findByText('check fail 1');

    fireEvent.click(result.getByText('重新检查'));

    await result.findByText('check suc 1');

    expect(result.queryByText('进入安装')).not.toBeNull();

    expect($.http).toMatchSnapshot();
  });

  test('check install fail', async () => {
    $.http = jest.fn().mockRejectedValueOnce('500');
    $.alert = jest.fn();

    render(<Index/>);

    await waitFor(() => {
      expect($.alert).toBeCalled();
    });

    expect($.http).toMatchSnapshot();
    expect($.alert).toMatchSnapshot();
  });

  test('submit fail', async () => {
    $.http = jest.fn()
      .mockResolvedValueOnce({
        ret: Ret.suc({
          data: {
            installRet: Ret.suc({
              data: [
                Ret.suc('check suc 1'),
                Ret.suc('check suc 2'),
              ],
            }),
            license: 'xxx',
          },
        }),
      })
      // 测试 rewrite
      .mockResolvedValueOnce({
        ret: Ret.suc(),
      })
      .mockRejectedValue(500);

    const result = render(<Index/>);

    const btn = await result.findByText('进入安装');
    fireEvent.click(btn);
    fireEvent.change(result.getByLabelText('数据库密码'), {target: {value: 'password'}});
    fireEvent.change(result.getByLabelText('管理员密码'), {target: {value: '123456'}});
    fireEvent.click(result.getByText('我已阅读并同意'));

    $.alert = jest.fn();

    fireEvent.click(result.getByText('安 装'));

    await waitFor(() => {
      expect($.http).toBeCalledTimes(3);
    });

    expect($.http).toMatchSnapshot();
    // 抛出异常，未执行 alert 逻辑
    expect($.alert).not.toBeCalled();
  });
});
