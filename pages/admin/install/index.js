/**
 * @layout false
 */
import {Component} from 'react';
import {Form, Button, Checkbox, Divider, List, Row, Col} from 'antd';
import {Box, Flex, Heading, Image} from '@mxjs/box';
import $, {Ret} from 'miaoxing';
import api from '@mxjs/api';
import {FormItem} from '@mxjs/a-form';
import {css, Global} from '@emotion/react';
import modal from '@mxjs/modal';
import {CheckCircleTwoTone, CloseCircleTwoTone} from '@ant-design/icons';

// TODO 读取主题
const SucIcon = () => <CheckCircleTwoTone twoToneColor="#5cb85c" style={{fontSize: '1.5rem'}}/>;
const ErrIcon = () => <CloseCircleTwoTone twoToneColor="#fa5b50" style={{fontSize: '1.5rem'}}/>;

export default class InstallIndex extends Component {
  state = {
    loading: false,
    loadingRetry: false,
    showForm: false,
    code: null,
    data: {},
  };

  requestDefaultUrlRewrite = false;

  async componentDidMount() {
    await this.checkInstall(false);
    await this.checkUrlRewrite();
  }

  async checkUrlRewrite() {
    // TODO 考虑使用其他接口，减少重复调用，
    $.get({
      url: '../admin-api/install',
      ignoreError: true,
    }).then(({ret}) => {
      if (ret && ret.isSuc()) {
        this.requestDefaultUrlRewrite = true;
      }
    }).catch(() => {
      // Ignore error
    });
  }

  handleClickRetry = async () => {
    await this.checkInstall();
  };

  checkInstall = async (showTips = true) => {
    this.setState({loadingRetry: true});
    try {
      const {ret} = await api.getCur();
      showTips && $.suc('检查完成');
      this.setState({loadingRetry: false, data: ret.data, code: ret.code});
    } catch (e) {
      await $.alert({
        content: '访问后端接口失败，请检查日志',
        okText: '刷新再试',
      });
      window.location.reload();
    }
  };

  handleClickNext = async () => {
    this.setState({showForm: true});
  };

  showAgreement = async (e) => {
    e.preventDefault();

    const license = this.state.data.license;
    const index = license.indexOf('\n');
    const title = license.substr(0, index);
    const content = license.substr(index + 1)
      .replace(/\n\n/g, '<br/><br/>')
      .replace(/\n/g, '');

    await modal.alert({
      title,
      content,
      html: true,
      size: 'lg',
    });
  };

  handleSubmit = async (values) => {
    this.setState({loading: true});

    values.requestDefaultUrlRewrite = this.requestDefaultUrlRewrite;

    try {
      const {ret} = await api.postCur({
        data: values,
        loading: true,
      });
      if (ret.isErr()) {
        $.alert(ret.message);
        return;
      }

      window.localStorage.removeItem('token');
      // 安装耗时较长，使用 alert 提示待用户确认
      await $.alert({
        content: ret.message,
        okText: '进入后台',
      });
      window.location.href = ret.next;
    } catch {
      // do nothing
    } finally {
      this.setState({loading: false});
    }
  };

  render() {
    return <Flex>
      <Global
        styles={css`
          body {
            background: #f5f8fa url(https://image-10001577.image.myqcloud.com/uploads/3/20190602/15594729401485.jpg) no-repeat center center fixed;
            background-size: cover;
          }
        `}
      />
      <Box
        width={700}
        mx="auto"
        my={12}
        p={12}
        bg="white"
      >
        <Box
          mb={4}
          textAlign="center"
        >
          <Image height="50px" src={$.url('images/logo.svg')}/>
        </Box>
        <Heading
          mb={12}
          textAlign="center"
          fontSize="lg"
          fontWeight="normal"
          color="muted"
        >
          安装
        </Heading>

        {!this.state.showForm && <>
          <List
            itemLayout="horizontal"
            dataSource={this.state.data?.installRet?.data || []}
            size="large"
            renderItem={item => {
              return (
                <List.Item>
                  <List.Item.Meta
                    avatar={Ret.isSuc(item) ? <SucIcon/> : <ErrIcon/>}
                    title={item.message}
                  />
                </List.Item>
              );
            }}
          />

          <Row justify="center">
            <Col>
              {Ret.isSuc(this.state.data?.installRet)
                ? <Button type="primary" onClick={this.handleClickNext}>进入安装</Button>
                : <Button type="primary" onClick={this.handleClickRetry}
                  loading={this.state.loadingRetry}>重新检查</Button>}
            </Col>
          </Row>
        </>}

        {this.state.showForm && <Form
          labelCol={{span: 8}}
          wrapperCol={{span: 8}}
          validateMessages={{
            required: '该项是必填的',
          }}
          initialValues={{
            dbHost: 'localhost',
            dbDbName: 'miaoxing',
            dbUser: 'root',
            dbTablePrefix: 'mx_',
            username: 'admin',
          }}
          onFinish={this.handleSubmit}
        >
          <FormItem label="数据库地址" name="dbHost" rules={[{required: true}]}
            extra="如果有端口号，使用`:`隔开"
          />
          <FormItem label="数据库名称" name="dbDbName" rules={[{required: true}]}/>
          <FormItem label="数据库用户名" name="dbUser" rules={[{required: true}]}/>
          <FormItem label="数据库密码" name="dbPassword" type="password" rules={[{required: true}]}/>
          <FormItem label="数据表前缀" name="dbTablePrefix" rules={[{required: true}]}/>

          <Divider/>

          <FormItem label="管理员用户名" name="username" rules={[{required: true}]}/>
          <FormItem label="管理员密码" name="password" type="password" rules={[{required: true}]}/>

          <Form.Item
            name="seed"
            wrapperCol={{offset: 8, span: 16}}
            valuePropName="checked"
          >
            <Checkbox>安装演示数据</Checkbox>
          </Form.Item>

          <Form.Item
            name="agree"
            wrapperCol={{offset: 8, span: 16}}
            valuePropName="checked"
            rules={[{required: true, message: '请阅读并同意《终端用户许可协议》'}]}
          >
            <Checkbox>我已阅读并同意<a href="#" onClick={this.showAgreement}>《终端用户许可协议》</a></Checkbox>
          </Form.Item>

          <Form.Item
            wrapperCol={{offset: 8, span: 8}}
          >
            <Button type="primary" htmlType="submit" block loading={this.state.loading}>
              安装
            </Button>
          </Form.Item>
        </Form>}
      </Box>
    </Flex>;
  }
}
